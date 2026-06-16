<?php

namespace App\Services\Lanjutan;

use App\Enums\UserRole;
use App\Models\AppUser;
use App\Models\Approval;
use App\Models\Attachment;
use App\Models\Pembayaran;
use App\Services\Integration\AlertService;
use App\Services\Security\AuditTrailService;
use App\Services\Transaksi\PembayaranService;
use App\Support\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Maker-Checker (Fasa 9) — suis induk Setting 'approval_enabled' (on/off, lalai OFF)
 * + had Setting 'approval_threshold' (RM, 0 = mati melalui had).
 * Bila aktif: bendahari yang membuat bayaran > had → payload borang disimpan dalam
 * jadual approval (PENDING, entity_id NULL); TIADA pembayaran/jurnal dicipta.
 * Admin/Pengerusi meluluskan → payload dimainkan semula melalui PembayaranService
 * (satu-satunya laluan ke jurnal) atau menolak. Lulus/tolak dikunci (lockForUpdate)
 * supaya tiada keputusan serentak (cegah double-approve → bayar dua kali).
 */
class ApprovalService
{
    public const KEY_THRESHOLD = 'approval_threshold';

    public const KEY_ENABLED = 'approval_enabled';

    public function __construct(
        private PembayaranService $pembayaran,
        private AuditTrailService $audit,
        private AlertService $alert,
    ) {
    }

    /** Had kelulusan semasa (RM). 0 = maker-checker dimatikan (melalui had). */
    public function had(?int $masjidId = null): float
    {
        return round((float) Setting::get(self::KEY_THRESHOLD, '0', $masjidId), 2);
    }

    /**
     * Suis induk maker-checker (ON/OFF). LALAI OFF — maker-checker dimatikan
     * melainkan admin menghidupkannya di /tetapan/kawalan. OFF → tiada bayaran
     * perlu kelulusan walau melebihi had.
     */
    public function aktif(?int $masjidId = null): bool
    {
        return in_array(Setting::get(self::KEY_ENABLED, 'off', $masjidId), ['on', '1', 'true'], true);
    }

    /** Adakah permohonan ini perlu kelulusan? (suis ON + bendahari + jumlah > had; admin lepas terus) */
    public function perluKelulusan(float $jumlah, ?AppUser $pengguna): bool
    {
        if (! $this->aktif()) {
            return false;
        }

        $had = $this->had();

        return $had > 0
            && $jumlah > $had
            && $pengguna?->role === UserRole::BENDAHARI;
    }

    /**
     * Simpan permohonan PENDING (payload borang penuh, JSON).
     * $jenis: 'BAYARAN' (createBayaran) atau 'REKUPMEN' (createRekupmen).
     */
    public function mohon(string $jenis, float $jumlah, array $payload, ?int $masjidId = null): Approval
    {
        $masjidId ??= app('current.masjid_id');
        unset($payload['dokumen']); // fail tidak boleh diserikan ke JSON

        $approval = Approval::withoutMasjidScope()->create([
            'masjid_id' => $masjidId,
            'entity'    => 'BAYARAN',
            'entity_id' => null, // diisi selepas lulus
            'amaun'     => number_format($jumlah, 2, '.', ''),
            'maker_id'  => app()->bound('current.user_id') ? app('current.user_id') : null,
            'status'    => 'PENDING',
            'remark'    => mb_substr(($jenis === 'REKUPMEN' ? 'REKUPMEN: ' : 'BAYARAN: ').($payload['deskripsi'] ?? $payload['pemohon'] ?? ''), 0, 300),
            'payload'   => json_encode(['_jenis' => $jenis] + $payload),
        ]);

        $this->audit->log('CREATE', 'approval', null, [
            'jenis' => $jenis, 'amaun' => number_format($jumlah, 2, '.', ''), 'had' => number_format($this->had($masjidId), 2, '.', ''),
        ], $approval->id, masjidId: $masjidId);

        // Notifikasi Telegram best-effort kepada admin
        $this->alert->hantar((int) $masjidId,
            "🔔 PERMOHONAN KELULUSAN BAHARU #{$approval->id}\n"
            ."Jenis: {$jenis}\nJumlah: RM".number_format($jumlah, 2)."\n"
            .'Pemohon: '.($payload['pemohon'] ?? '-')."\n"
            .'Sila semak di halaman Kelulusan.');

        return $approval;
    }

    /** Luluskan — mainkan semula payload melalui PembayaranService. */
    public function lulus(Approval $approval, ?int $checkerId = null): Pembayaran
    {
        if ($approval->status !== 'PENDING') {
            throw new InvalidArgumentException("Permohonan #{$approval->id} telah pun diputuskan ({$approval->status}).");
        }

        $payload = json_decode((string) $approval->payload, true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException("Permohonan #{$approval->id} tiada payload sah.");
        }

        $jenis = $payload['_jenis'] ?? 'BAYARAN';
        unset($payload['_jenis']);
        // Dokumen sokongan distash semasa permohonan (lihat BelanjaController::stashLampiran).
        $lampiran = is_array($payload['_lampiran'] ?? null) ? $payload['_lampiran'] : [];
        unset($payload['_lampiran']);

        return DB::transaction(function () use ($approval, $payload, $jenis, $checkerId, $lampiran) {
            // Kunci baris + semak status SEMULA secara atomik — cegah lulus serentak
            // (double-approve → bayar dua kali). Transaksi kedua menunggu kunci, melihat
            // status sudah APPROVED, lalu gagal di sini sebelum mencipta pembayaran kedua.
            $semasa = Approval::withoutMasjidScope()->whereKey($approval->id)->lockForUpdate()->first();
            if (! $semasa || $semasa->status !== 'PENDING') {
                throw new InvalidArgumentException("Permohonan #{$approval->id} telah pun diputuskan.");
            }

            $pembayaran = $jenis === 'REKUPMEN'
                ? $this->pembayaran->createRekupmen($payload)
                : $this->pembayaran->createBayaran($payload);

            // Pautkan lampiran yg distash kpd pembayaran sebenar (masjid_id dicop auto
            // oleh BelongsToMasjid = masjid konteks = masjid permohonan).
            foreach ($lampiran as $l) {
                if (empty($l['file_path'])) {
                    continue;
                }
                Attachment::create([
                    'owner_type'  => 'BAYARAN',
                    'owner_id'    => $pembayaran->id,
                    'file_path'   => $l['file_path'],
                    'file_name'   => $l['file_name'] ?? null,
                    'mime'        => $l['mime'] ?? null,
                    'size_bytes'  => $l['size_bytes'] ?? null,
                    'uploaded_by' => $l['uploaded_by'] ?? null,
                    'uploaded_at' => $l['uploaded_at'] ?? now(),
                ]);
            }

            $semasa->update([
                'status'     => 'APPROVED',
                'entity_id'  => $pembayaran->id,
                'checker_id' => $checkerId ?? (app()->bound('current.user_id') ? app('current.user_id') : null),
                'decided_at' => now(),
            ]);

            $this->audit->log('APPROVE', 'approval', ['status' => 'PENDING'], [
                'status' => 'APPROVED', 'pembayaran_id' => $pembayaran->id, 'jenis' => $jenis, 'lampiran' => count($lampiran),
            ], $semasa->id);

            return $pembayaran;
        });
    }

    /** Tolak permohonan (tiada kesan kewangan). */
    public function tolak(Approval $approval, string $sebab = '', ?int $checkerId = null): Approval
    {
        if ($approval->status !== 'PENDING') {
            throw new InvalidArgumentException("Permohonan #{$approval->id} telah pun diputuskan ({$approval->status}).");
        }

        return DB::transaction(function () use ($approval, $sebab, $checkerId) {
            // Kunci baris + semak status SEMULA (selari lulus — cegah keputusan serentak).
            $semasa = Approval::withoutMasjidScope()->whereKey($approval->id)->lockForUpdate()->first();
            if (! $semasa || $semasa->status !== 'PENDING') {
                throw new InvalidArgumentException("Permohonan #{$approval->id} telah pun diputuskan.");
            }

            // Permohonan ditolak → tiada pembayaran; buang fail dokumen yg distash (jangan
            // tinggalkan PII yatim di storan). Tiada row attachment lagi (dicipta masa lulus).
            $payload = json_decode((string) $semasa->payload, true);
            foreach ((is_array($payload) ? ($payload['_lampiran'] ?? []) : []) as $l) {
                if (! empty($l['file_path'])) {
                    Storage::disk('local')->delete($l['file_path']);
                }
            }

            $semasa->update([
                'status'     => 'REJECTED',
                'checker_id' => $checkerId ?? (app()->bound('current.user_id') ? app('current.user_id') : null),
                'decided_at' => now(),
                'remark'     => mb_substr(trim(($semasa->remark ?? '').' | DITOLAK: '.$sebab, ' |'), 0, 300),
            ]);

            $this->audit->log('UPDATE', 'approval', ['status' => 'PENDING'],
                ['status' => 'REJECTED', 'sebab' => mb_substr($sebab, 0, 200)], $semasa->id);

            return $semasa->fresh();
        });
    }
}

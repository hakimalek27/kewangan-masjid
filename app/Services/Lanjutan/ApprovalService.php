<?php

namespace App\Services\Lanjutan;

use App\Enums\UserRole;
use App\Models\AppUser;
use App\Models\Approval;
use App\Models\Pembayaran;
use App\Services\Integration\AlertService;
use App\Services\Security\AuditTrailService;
use App\Services\Transaksi\PembayaranService;
use App\Support\Setting;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Maker-Checker (Fasa 9) — Setting 'approval_threshold' (RM, 0 = mati).
 * Bendahari yang membuat bayaran > had → payload borang disimpan dalam
 * jadual approval (PENDING, entity_id NULL); TIADA pembayaran/jurnal dicipta.
 * Admin/Pengerusi meluluskan → payload dimainkan semula melalui
 * PembayaranService (satu-satunya laluan ke jurnal) atau menolak.
 */
class ApprovalService
{
    public const KEY_THRESHOLD = 'approval_threshold';

    public function __construct(
        private PembayaranService $pembayaran,
        private AuditTrailService $audit,
        private AlertService $alert,
    ) {
    }

    /** Had kelulusan semasa (RM). 0 = maker-checker dimatikan. */
    public function had(?int $masjidId = null): float
    {
        return round((float) Setting::get(self::KEY_THRESHOLD, '0', $masjidId), 2);
    }

    /** Adakah permohonan ini perlu kelulusan? (bendahari + jumlah > had; admin lepas terus) */
    public function perluKelulusan(float $jumlah, ?AppUser $pengguna): bool
    {
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

        return DB::transaction(function () use ($approval, $payload, $jenis, $checkerId) {
            $pembayaran = $jenis === 'REKUPMEN'
                ? $this->pembayaran->createRekupmen($payload)
                : $this->pembayaran->createBayaran($payload);

            $approval->update([
                'status'     => 'APPROVED',
                'entity_id'  => $pembayaran->id,
                'checker_id' => $checkerId ?? (app()->bound('current.user_id') ? app('current.user_id') : null),
                'decided_at' => now(),
            ]);

            $this->audit->log('APPROVE', 'approval', ['status' => 'PENDING'], [
                'status' => 'APPROVED', 'pembayaran_id' => $pembayaran->id, 'jenis' => $jenis,
            ], $approval->id);

            return $pembayaran;
        });
    }

    /** Tolak permohonan (tiada kesan kewangan). */
    public function tolak(Approval $approval, string $sebab = '', ?int $checkerId = null): Approval
    {
        if ($approval->status !== 'PENDING') {
            throw new InvalidArgumentException("Permohonan #{$approval->id} telah pun diputuskan ({$approval->status}).");
        }

        $approval->update([
            'status'     => 'REJECTED',
            'checker_id' => $checkerId ?? (app()->bound('current.user_id') ? app('current.user_id') : null),
            'decided_at' => now(),
            'remark'     => mb_substr(trim(($approval->remark ?? '').' | DITOLAK: '.$sebab, ' |'), 0, 300),
        ]);

        $this->audit->log('UPDATE', 'approval', ['status' => 'PENDING'],
            ['status' => 'REJECTED', 'sebab' => mb_substr($sebab, 0, 200)], $approval->id);

        return $approval->fresh();
    }
}

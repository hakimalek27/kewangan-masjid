<?php

namespace App\Services\Ai;

use App\Models\Attachment;
use App\Models\DocInbox;
use App\Models\TxnDraft;
use App\Services\Security\AuditTrailService;
use App\Services\Transaksi\KutipanService;
use App\Services\Transaksi\PembayaranService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Pengesahan draf AI oleh bendahari — SATU-SATUNYA laluan draf → jurnal.
 * AI tidak pernah memanggil servis ini; hanya controller web (role
 * admin/bendahari) selepas semakan manusia.
 */
class DraftService
{
    public function __construct(
        private KutipanService $kutipan,
        private PembayaranService $pembayaran,
        private AuditTrailService $audit,
    ) {
    }

    /** Sahkan draf: gabung medan draf + override borang → rekod transaksi sebenar. */
    public function confirm(TxnDraft $draf, array $override = []): TxnDraft
    {
        if ($draf->status !== 'PENDING_REVIEW') {
            throw new InvalidArgumentException('Draf ini telah pun disemak (status: '.$draf->status.').');
        }

        $data = $this->gabung($draf, $override);

        $draf = DB::transaction(function () use ($draf, $data) {
            $rekod = $draf->jenis === 'BAYARAN'
                ? $this->rekodBayaran($data)
                : $this->rekodKutipan($data);

            $this->lampirkanFail($draf, $rekod->id);

            $draf->update([
                'status' => 'POSTED',
                'posted_recno' => $rekod->id,
                'coa_id' => $data['coa_id'],
                'tarikh' => $data['tarikh'],
                'jumlah' => $data['jumlah'],
                'reviewed_by' => app()->bound('current.user_id') ? app('current.user_id') : null,
                'reviewed_at' => now(),
            ]);

            if ($draf->inbox_id) {
                DocInbox::withoutMasjidScope()->whereKey($draf->inbox_id)->update(['status' => 'CONFIRMED']);
            }

            // 'APPROVE' = nilai enum audit_trail.action untuk pengesahan
            $this->audit->log('APPROVE', 'txn_draft', null, [
                'jenis' => $draf->jenis, 'jumlah' => (string) $data['jumlah'], 'recno' => $rekod->id,
            ], $draf->id);

            return $draf->fresh();
        });

        $rujukan = $draf->jenis === 'BAYARAN'
            ? 'baucer #'.$draf->posted_recno
            : 'resit #'.$draf->posted_recno;
        $this->balasTelegram($draf, "✅ Draf #{$draf->id} disahkan & direkodkan ({$rujukan}).");

        // Webhook draft.confirmed (best-effort) — satu seam untuk pengesahan
        // tunggal & pukal (DrafController + DrafBulkController guna confirm ini)
        try {
            app(\App\Services\Api\WebhookDispatcher::class)->dispatch('draft.confirmed', [
                'draft_id' => $draf->id, 'jenis' => $draf->jenis,
                'posted_recno' => $draf->posted_recno, 'jumlah' => (string) $draf->jumlah,
            ], (int) $draf->masjid_id);
        } catch (Throwable) {
            // abai — notifikasi/webhook tidak menjejaskan transaksi yang sudah direkod
        }

        return $draf;
    }

    /** Tolak draf — tiada kesan jurnal langsung. */
    public function reject(TxnDraft $draf, string $sebab = ''): TxnDraft
    {
        if ($draf->status !== 'PENDING_REVIEW') {
            throw new InvalidArgumentException('Draf ini telah pun disemak (status: '.$draf->status.').');
        }

        DB::transaction(function () use ($draf, $sebab) {
            $draf->update([
                'status' => 'REJECTED',
                'reviewed_by' => app()->bound('current.user_id') ? app('current.user_id') : null,
                'reviewed_at' => now(),
            ]);

            if ($draf->inbox_id) {
                DocInbox::withoutMasjidScope()->whereKey($draf->inbox_id)->update(['status' => 'REJECTED']);
            }

            // 'UPDATE' = enum audit_trail terdekat untuk penolakan draf (bukan rekod kewangan)
            $this->audit->log('UPDATE', 'txn_draft', ['status' => 'PENDING_REVIEW'],
                ['status' => 'REJECTED', 'sebab' => $sebab], $draf->id);
        });

        $draf = $draf->fresh();
        $this->balasTelegram($draf, "❌ Draf #{$draf->id} ditolak".($sebab !== '' ? " — {$sebab}" : '.'));

        return $draf;
    }

    /** Gabung medan draf dengan override borang (borang menang). */
    private function gabung(TxnDraft $draf, array $override): array
    {
        $medan = ['tarikh', 'jumlah', 'coa_id', 'penerima', 'no_rujukan', 'kaedah', 'deskripsi', 'bank_account_id'];

        $data = [];
        foreach ($medan as $m) {
            $nilai = array_key_exists($m, $override) && $override[$m] !== null && $override[$m] !== ''
                ? $override[$m]
                : $draf->{$m};
            $data[$m] = $nilai;
        }

        $data['tarikh'] = $data['tarikh'] instanceof \DateTimeInterface
            ? $data['tarikh']->format('Y-m-d')
            : (string) ($data['tarikh'] ?: now()->format('Y-m-d'));

        if (empty($data['coa_id'])) {
            throw new InvalidArgumentException('Sila pilih kod akaun (COA) sebelum mengesahkan draf.');
        }
        if (empty($data['jumlah']) || (float) $data['jumlah'] <= 0) {
            throw new InvalidArgumentException('Jumlah draf mesti lebih daripada sifar.');
        }

        return $data;
    }

    private function rekodKutipan(array $data): \App\Models\Kutipan
    {
        $kaedah = $this->petaKaedahKutipan($data['kaedah']);

        if ($kaedah !== 'TUNAI' && empty($data['bank_account_id'])) {
            throw new InvalidArgumentException('Sila pilih akaun bank untuk kutipan bukan tunai.');
        }

        return $this->kutipan->create([
            'jenis' => 'BIASA',
            'tarikh' => $data['tarikh'],
            'coa_id' => (int) $data['coa_id'],
            'kaedah' => $kaedah,
            'jumlah' => $data['jumlah'],
            'auto_resit' => empty($data['no_rujukan']),
            'no_resit' => $data['no_rujukan'] ?: null,
            'nama_pemberi' => $data['penerima'],
            'bank_account_id' => $data['bank_account_id'] ?: null,
            'deskripsi' => $data['deskripsi'],
        ]);
    }

    private function rekodBayaran(array $data): \App\Models\Pembayaran
    {
        if (empty($data['bank_account_id'])) {
            throw new InvalidArgumentException('Sila pilih akaun bank untuk bayaran ini.');
        }

        return $this->pembayaran->createBayaran([
            'tar_lulus' => $data['tarikh'],
            'coa_id' => (int) $data['coa_id'],
            'jumlah' => $data['jumlah'],
            'cara_bayar' => $this->petaCaraBayar($data['kaedah']),
            'auto_baucer' => true,
            'pemohon' => $data['penerima'],
            'deskripsi' => $data['deskripsi'],
            'bank_account_id' => (int) $data['bank_account_id'],
            'no_acct' => $data['no_rujukan'] ? mb_substr($data['no_rujukan'], 0, 40) : null,
        ]);
    }

    /** Kaedah AI → enum kutipan: EFT/QR→BANK_TRANSFER_QR, TUNAI→TUNAI, CEK→CEK. */
    private function petaKaedahKutipan(?string $kaedah): string
    {
        return match (strtoupper(trim((string) $kaedah))) {
            'TUNAI' => 'TUNAI',
            'CEK' => 'CEK',
            default => 'BANK_TRANSFER_QR', // EFT, QR, BANK_TRANSFER_QR, kosong
        };
    }

    /** Kaedah AI → enum pembayaran: QR/EFT→EFT, CEK→CEK, TUNAI→NON_CASH. */
    private function petaCaraBayar(?string $kaedah): string
    {
        return match (strtoupper(trim((string) $kaedah))) {
            'CEK' => 'CEK',
            'TUNAI' => 'NON_CASH',
            default => 'EFT', // QR, EFT, kosong
        };
    }

    /** Lampirkan fail asal inbox pada transaksi yang direkod. */
    private function lampirkanFail(TxnDraft $draf, int $ownerId): void
    {
        if (!$draf->inbox_id) {
            return;
        }

        $inbox = DocInbox::withoutMasjidScope()->find($draf->inbox_id);
        if (!$inbox || !$inbox->file_path) {
            return;
        }

        Attachment::create([
            'masjid_id' => $draf->masjid_id,
            'owner_type' => $draf->jenis === 'BAYARAN' ? 'BAYARAN' : 'KUTIPAN',
            'owner_id' => $ownerId,
            'file_path' => $inbox->file_path,
            'file_name' => basename($inbox->file_path),
            'mime' => $inbox->file_type === 'PDF' ? 'application/pdf' : 'image/jpeg',
            'size_bytes' => Storage::disk('local')->exists($inbox->file_path)
                ? Storage::disk('local')->size($inbox->file_path)
                : null,
            'uploaded_by' => app()->bound('current.user_id') ? app('current.user_id') : null,
        ]);
    }

    /** Balasan Telegram best-effort — kegagalan bot tidak membatalkan transaksi. */
    private function balasTelegram(TxnDraft $draf, string $teks): void
    {
        try {
            if (!$draf->inbox_id) {
                return;
            }
            $inbox = DocInbox::withoutMasjidScope()->find($draf->inbox_id);
            if (!$inbox) {
                return;
            }
            TelegramService::forMasjid((int) $draf->masjid_id)
                ->sendMessage($inbox->tg_chat_id, $teks);
        } catch (Throwable) {
            // abai — notifikasi sahaja
        }
    }
}

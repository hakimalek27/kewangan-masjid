<?php

namespace App\Observers;

use App\Models\Pembayaran;
use App\Observers\Concerns\EmitsTransactionWebhook;
use App\Observers\Concerns\QueuesDualWrite;
use App\Observers\Concerns\QueuesTransactionBackup;

/**
 * Backup per-transaksi + dual-write SPPKMS + webhook transaction.created/voided
 * pembayaran (BAYARAN/ASET/REKUPMEN) — berjalan SELEPAS commit sahaja, tidak
 * menyentuh logik PembayaranService.
 */
class PembayaranObserver
{
    use EmitsTransactionWebhook, QueuesDualWrite, QueuesTransactionBackup;

    public bool $afterCommit = true;

    public function created(Pembayaran $pembayaran): void
    {
        $this->masukBarisanBackup($pembayaran, 'pembayaran');
        $this->masukBarisanDualWrite($pembayaran, 'BAYARAN');
        $this->emitWebhook($pembayaran, 'payment', 'transaction.created');
    }

    public function updated(Pembayaran $pembayaran): void
    {
        if ($pembayaran->wasChanged('status')) {
            $this->masukBarisanBackup($pembayaran, 'pembayaran');

            // VOID bayaran = status CANCELLED — jika sudah DONE di SPPKMS
            // lama, rekod lama perlu dipadam MANUAL (tiada endpoint selamat)
            if ($pembayaran->status === 'CANCELLED') {
                $this->tandaPerluPadamManual($pembayaran, 'BAYARAN');
                $this->emitWebhook($pembayaran, 'payment', 'transaction.voided');
            }
        }
    }
}

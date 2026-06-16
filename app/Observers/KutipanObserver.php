<?php

namespace App\Observers;

use App\Models\Kutipan;
use App\Observers\Concerns\EmitsTransactionWebhook;
use App\Observers\Concerns\QueuesDualWrite;
use App\Observers\Concerns\QueuesTransactionBackup;

/**
 * Backup per-transaksi + dual-write SPPKMS + webhook transaction.created/voided
 * kutipan — berjalan SELEPAS commit sahaja ($afterCommit), tidak menyentuh
 * logik kewangan KutipanService.
 */
class KutipanObserver
{
    use EmitsTransactionWebhook, QueuesDualWrite, QueuesTransactionBackup;

    public bool $afterCommit = true;

    public function created(Kutipan $kutipan): void
    {
        $this->masukBarisanBackup($kutipan, 'kutipan');
        $this->masukBarisanDualWrite($kutipan, 'KUTIPAN');
        $this->emitWebhook($kutipan, 'receipt', 'transaction.created');
    }

    public function updated(Kutipan $kutipan): void
    {
        if ($kutipan->wasChanged('status')) {
            $this->masukBarisanBackup($kutipan, 'kutipan');

            // "Padam" kutipan = status DELETED — jika sudah DONE di SPPKMS
            // lama, rekod lama perlu dipadam MANUAL (tiada endpoint selamat)
            if ($kutipan->status === 'DELETED') {
                $this->tandaPerluPadamManual($kutipan, 'KUTIPAN');
                $this->emitWebhook($kutipan, 'receipt', 'transaction.voided');
            }
        }
    }
}

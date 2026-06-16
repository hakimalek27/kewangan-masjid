<?php

namespace App\Observers;

use App\Enums\SourceType;
use App\Models\JournalVoucher;
use App\Observers\Concerns\QueuesTransactionBackup;

/**
 * Backup per-transaksi voucher jurnal yang TIDAK diliputi observer
 * Kutipan/Pembayaran (elak duplikasi barisan):
 *   diliputi di sini : JURNAL, FD, OPENING, CEK_BATAL, PELUPUSAN
 *   dilangkau        : KUTIPAN, DIVIDEN (KutipanObserver),
 *                      BAYARAN, ASET, REKUPMEN (PembayaranObserver)
 */
class JournalVoucherObserver
{
    use QueuesTransactionBackup;

    public bool $afterCommit = true;

    private const DILIPUTI_OBSERVER_LAIN = [
        'KUTIPAN', 'DIVIDEN', 'BAYARAN', 'ASET', 'REKUPMEN',
    ];

    public function created(JournalVoucher $voucher): void
    {
        if (!$this->diliputiObserverLain($voucher)) {
            $this->masukBarisanBackup($voucher, 'journal_voucher', $voucher->id);
        }
    }

    public function updated(JournalVoucher $voucher): void
    {
        if ($voucher->wasChanged('status') && !$this->diliputiObserverLain($voucher)) {
            $this->masukBarisanBackup($voucher, 'journal_voucher', $voucher->id);
        }
    }

    private function diliputiObserverLain(JournalVoucher $voucher): bool
    {
        $jenis = $voucher->source_type instanceof SourceType
            ? $voucher->source_type->value
            : (string) $voucher->source_type;

        return in_array($jenis, self::DILIPUTI_OBSERVER_LAIN, true);
    }
}

<?php

namespace App\Services\Transaksi;

use App\Enums\SequenceType;
use App\Enums\SourceType;
use App\Models\JournalVoucher;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\NumberSequenceService;
use App\Services\Security\AuditTrailService;
use Illuminate\Support\Facades\DB;

/**
 * Perbelanjaan Bukan Tunai / Jurnal manual (belanja_jurnal.php) —
 * susut nilai, akruan, pembetulan. Dr (600/650) / Cr (SNT/lain) bebas dipilih.
 * Siri voucher: JNL#####. TIADA pergerakan tunai/bank.
 */
class BelanjaJurnalService
{
    public function __construct(
        private JournalService $journal,
        private NumberSequenceService $seq,
        private AuditTrailService $audit,
    ) {
    }

    public function create(string $tarikh, int $drCoaId, int $crCoaId, string|float $jumlah, string $deskripsi, ?string $periodYm = null): JournalVoucher
    {
        return DB::transaction(function () use ($tarikh, $drCoaId, $crCoaId, $jumlah, $deskripsi, $periodYm) {
            $ref = $this->seq->next(SequenceType::JNL);

            $voucher = $this->journal->post(
                tarikh: $tarikh,
                sourceType: SourceType::JURNAL,
                sourceId: null,
                deskripsi: $deskripsi,
                lines: [
                    ['coa_id' => $drCoaId, 'debit' => $jumlah, 'kredit' => 0, 'memo' => $deskripsi],
                    ['coa_id' => $crCoaId, 'debit' => 0, 'kredit' => $jumlah, 'memo' => $deskripsi],
                ],
                voucherRef: $ref,
                periodYm: $periodYm,
            );

            $this->audit->log('CREATE', 'journal_voucher', null, ['ref' => $ref, 'jumlah' => (string) $jumlah], $voucher->id);

            return $voucher;
        });
    }
}

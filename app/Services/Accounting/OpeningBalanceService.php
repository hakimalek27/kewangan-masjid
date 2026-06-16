<?php

namespace App\Services\Accounting;

use App\Enums\SourceType;
use App\Models\JournalVoucher;
use App\Models\OpeningBalance;
use App\Services\Security\AuditTrailService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Baki Awal tahun: jurnal OB-<tahun> — Dr Bank/Aset, Cr 100-10000 Dana Terkumpul
 * (baris pengimbang automatik). Dikunci selepas muktamad; "Reset & Edit Semula"
 * membatalkan voucher & membuka kunci.
 */
class OpeningBalanceService
{
    public function __construct(
        private JournalService $journal,
        private VoidService $void,
        private AuditTrailService $audit,
    ) {
    }

    /**
     * @param array<int, array{coa_id:int, amaun:numeric, side:'D'|'C'}> $rows
     */
    public function set(int $tahun, array $rows, ?int $masjidId = null): JournalVoucher
    {
        $masjidId ??= app('current.masjid_id');

        return DB::transaction(function () use ($tahun, $rows, $masjidId) {
            $sediaAda = OpeningBalance::withoutMasjidScope()
                ->where('masjid_id', $masjidId)
                ->where('tahun', $tahun)
                ->where('is_locked', 1)
                ->exists();
            if ($sediaAda) {
                throw new RuntimeException("Baki awal {$tahun} telah dikunci. Guna 'Reset & Edit Semula' dahulu.");
            }

            OpeningBalance::withoutMasjidScope()
                ->where('masjid_id', $masjidId)
                ->where('tahun', $tahun)
                ->delete();

            $lines  = [];
            $netDr  = '0.00';
            foreach ($rows as $r) {
                $amaun = number_format((float) $r['amaun'], 2, '.', '');
                if (bccomp($amaun, '0', 2) <= 0) {
                    continue;
                }

                OpeningBalance::withoutMasjidScope()->create([
                    'masjid_id' => $masjidId,
                    'tahun'     => $tahun,
                    'coa_id'    => $r['coa_id'],
                    'amaun'     => $amaun,
                    'side'      => $r['side'],
                ]);

                if ($r['side'] === 'D') {
                    $lines[] = ['coa_id' => $r['coa_id'], 'debit' => $amaun, 'kredit' => 0, 'memo' => "Baki awal {$tahun}"];
                    $netDr = bcadd($netDr, $amaun, 2);
                } else {
                    $lines[] = ['coa_id' => $r['coa_id'], 'debit' => 0, 'kredit' => $amaun, 'memo' => "Baki awal {$tahun}"];
                    $netDr = bcsub($netDr, $amaun, 2);
                }
            }

            if (!$lines) {
                throw new RuntimeException('Tiada baki awal untuk disimpan.');
            }

            // Baris pengimbang → 100-10000 Dana Terkumpul
            $dana = $this->journal->coaByKod(config('sppkms.coa.dana_terkumpul'), $masjidId);
            if (bccomp($netDr, '0', 2) > 0) {
                $lines[] = ['coa_id' => $dana->id, 'debit' => 0, 'kredit' => $netDr, 'memo' => "Dana terkumpul (baki awal {$tahun})"];
            } elseif (bccomp($netDr, '0', 2) < 0) {
                $lines[] = ['coa_id' => $dana->id, 'debit' => bcmul($netDr, '-1', 2), 'kredit' => 0, 'memo' => "Dana terkumpul (baki awal {$tahun})"];
            }

            $voucher = $this->journal->post(
                tarikh: "{$tahun}-01-01",
                sourceType: SourceType::OPENING,
                sourceId: null,
                deskripsi: "BAKI AWAL {$tahun}",
                lines: $lines,
                voucherRef: "OB-{$tahun}",
                periodYm: sprintf('%d-00', $tahun), // tempoh pembukaan — sebelum Januari (konvensyen migrasi)
                masjidId: $masjidId,
            );

            $this->audit->log('CREATE', 'opening_balance', null, ['tahun' => $tahun, 'voucher' => "OB-{$tahun}"], null, null, $masjidId);

            return $voucher;
        });
    }

    public function lock(int $tahun, ?int $masjidId = null): void
    {
        $masjidId ??= app('current.masjid_id');

        OpeningBalance::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('tahun', $tahun)
            ->update(['is_locked' => 1, 'locked_at' => now()]);

        $this->audit->log('APPROVE', 'opening_balance', null, ['tahun' => $tahun, 'status' => 'LOCKED'], null, null, $masjidId);
    }

    /** "Reset & Edit Semula" — batal voucher OB & buka kunci. */
    public function resetAndUnlock(int $tahun, ?int $masjidId = null): void
    {
        $masjidId ??= app('current.masjid_id');

        DB::transaction(function () use ($tahun, $masjidId) {
            $voucher = JournalVoucher::withoutMasjidScope()
                ->where('masjid_id', $masjidId)
                ->where('voucher_ref', "OB-{$tahun}")
                ->where('status', 'POSTED')
                ->first();

            if ($voucher) {
                $pembalik = $this->void->voidVoucher($voucher, "Reset baki awal {$tahun}");

                // Bebaskan ref OB-<tahun> untuk set semula (UNIQUE voucher_ref);
                // sejarah kekal — hanya ref ditanda dengan id voucher
                $voucher->update(['voucher_ref' => mb_substr("OB-{$tahun}-V{$voucher->id}", 0, 40)]);
                $pembalik->update(['voucher_ref' => mb_substr("RV-OB-{$tahun}-V{$voucher->id}", 0, 40)]);
            }

            OpeningBalance::withoutMasjidScope()
                ->where('masjid_id', $masjidId)
                ->where('tahun', $tahun)
                ->update(['is_locked' => 0, 'locked_at' => null]);
        });
    }
}

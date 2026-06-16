<?php

namespace App\Services\Accounting;

use App\Enums\SourceType;
use App\Exceptions\UnbalancedJournalException;
use App\Models\Coa;
use App\Models\JournalEntry;
use App\Models\JournalVoucher;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * SATU-SATUNYA pintu untuk menulis ke jurnal. Menjamin:
 *   1. Σdebit = Σkredit (bccomp 2 titik perpuluhan) — jika tidak, transaksi DITOLAK.
 *   2. Setiap baris: debit≥0, kredit≥0, bukan kedua-duanya >0 (selari CHECK DB).
 *   3. COA mesti wujud, milik masjid, boleh-pos (bukan kod kepala) & aktif.
 *   4. Tempoh (period_ym) tidak terkunci.
 * Semua laporan dikira real-time daripada journal_entry (status POSTED sahaja).
 */
class JournalService
{
    public function __construct(private PeriodService $period)
    {
    }

    /**
     * @param array<int, array{coa_id:int, debit:numeric, kredit:numeric, memo?:string}> $lines
     */
    public function post(
        string $tarikh,
        SourceType $sourceType,
        ?int $sourceId,
        string $deskripsi,
        array $lines,
        string $voucherRef,
        ?string $periodYm = null,
        ?int $masjidId = null,
        ?int $createdBy = null,
    ): JournalVoucher {
        $masjidId  ??= app('current.masjid_id');
        $createdBy ??= app()->bound('current.user_id') ? app('current.user_id') : null;
        $periodYm  ??= substr($tarikh, 0, 7);

        $this->validateLines($lines, $masjidId);
        $this->period->assertOpen($periodYm, $masjidId);

        return DB::transaction(function () use ($tarikh, $sourceType, $sourceId, $deskripsi, $lines, $voucherRef, $periodYm, $masjidId, $createdBy) {
            $voucher = JournalVoucher::withoutMasjidScope()->create([
                'masjid_id'   => $masjidId,
                'voucher_ref' => $voucherRef,
                'tarikh'      => $tarikh,
                'period_ym'   => $periodYm,
                'deskripsi'   => mb_substr($deskripsi, 0, 500),
                'source_type' => $sourceType->value,
                'source_id'   => $sourceId,
                'status'      => 'POSTED',
                'created_by'  => $createdBy,
            ]);

            $rows = [];
            foreach ($lines as $line) {
                $rows[] = [
                    'voucher_id' => $voucher->id,
                    'coa_id'     => (int) $line['coa_id'],
                    'memo'       => mb_substr($line['memo'] ?? '', 0, 300),
                    'debit'      => number_format((float) $line['debit'], 2, '.', ''),
                    'kredit'     => number_format((float) $line['kredit'], 2, '.', ''),
                ];
            }
            JournalEntry::insert($rows);

            return $voucher;
        });
    }

    /**
     * @throws UnbalancedJournalException|InvalidArgumentException
     */
    private function validateLines(array $lines, int $masjidId): void
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('Voucher jurnal mesti ada sekurang-kurangnya 2 baris (Dr & Cr).');
        }

        $totalD = '0.00';
        $totalK = '0.00';

        foreach ($lines as $line) {
            $d = number_format((float) ($line['debit'] ?? 0), 2, '.', '');
            $k = number_format((float) ($line['kredit'] ?? 0), 2, '.', '');

            if (bccomp($d, '0', 2) < 0 || bccomp($k, '0', 2) < 0) {
                throw new InvalidArgumentException('Nilai debit/kredit tidak boleh negatif.');
            }
            if (bccomp($d, '0', 2) > 0 && bccomp($k, '0', 2) > 0) {
                throw new InvalidArgumentException('Satu baris tidak boleh ada debit DAN kredit serentak.');
            }

            $totalD = bcadd($totalD, $d, 2);
            $totalK = bcadd($totalK, $k, 2);
        }

        if (bccomp($totalD, $totalK, 2) !== 0) {
            throw new UnbalancedJournalException($totalD, $totalK);
        }
        if (bccomp($totalD, '0', 2) <= 0) {
            throw new InvalidArgumentException('Jumlah voucher mesti lebih daripada sifar.');
        }

        // Sahkan semua COA: milik masjid, boleh-pos, aktif
        $coaIds = array_unique(array_map(fn ($l) => (int) $l['coa_id'], $lines));
        $sah = Coa::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->whereIn('id', $coaIds)
            ->where('is_header', 0)
            ->where('is_active', 1)
            ->pluck('id')
            ->all();

        $tidakSah = array_diff($coaIds, $sah);
        if ($tidakSah) {
            throw new InvalidArgumentException('COA tidak sah/kod kepala/tidak aktif: id '.implode(',', $tidakSah));
        }
    }

    /** Cari COA ikut kod (cth '250-05010') untuk masjid semasa. */
    public function coaByKod(string $kod, ?int $masjidId = null): Coa
    {
        $masjidId ??= app('current.masjid_id');

        return Coa::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('kod', $kod)
            ->firstOrFail();
    }
}

<?php

namespace App\Services\Lanjutan;

use App\Enums\SequenceType;
use App\Enums\SourceType;
use App\Models\DepreciationSchedule;
use App\Models\FixedAsset;
use App\Models\JournalVoucher;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\NumberSequenceService;
use App\Services\Security\AuditTrailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Susut nilai garis lurus bulanan + pelupusan aset (Fasa 9).
 *
 *   Susut bulanan = kos × (depn_rate_pct/100) / 12
 *                   (atau kos / useful_life_years / 12 jika kadar tiada)
 *   Berhenti apabila accumulated_depn >= kos.
 *
 * Jurnal susut nilai (per aset per bulan, idempoten via depreciation_schedule):
 *   Dr 650-10000 BELANJA SUSUT NILAI / Cr snt_coa_id aset (kontra SNT)
 *
 * Pelupusan (source_type PELUPUSAN):
 *   Dr SNT (accumulated_depn) + Dr 600-99990 (baki nilai buku) / Cr akaun aset (KOS PENUH)
 */
class DepreciationService
{
    public function __construct(
        private JournalService $journal,
        private NumberSequenceService $seq,
        private AuditTrailService $audit,
    ) {
    }

    /** Susut nilai sebulan (garis lurus), 2 titik perpuluhan. 0.0 = tiada kadar/usia guna. */
    public function susutBulanan(FixedAsset $aset): float
    {
        $kos = (float) $aset->kos;
        if ($kos <= 0) {
            return 0.0;
        }

        if ((float) $aset->depn_rate_pct > 0) {
            return round($kos * ((float) $aset->depn_rate_pct / 100) / 12, 2);
        }
        if ((int) $aset->useful_life_years > 0) {
            return round($kos / (int) $aset->useful_life_years / 12, 2);
        }

        return 0.0;
    }

    /**
     * Jana & pos susut nilai semua aset AKTIF untuk bulan tertentu (idempoten —
     * aset yang sudah diposkan bulan itu dilangkau). Pulangkan ringkasan.
     */
    public function janaBulan(int $tahun, int $bulan, ?int $masjidId = null): array
    {
        $masjidId ??= app('current.masjid_id');
        $coaSusut = $this->journal->coaByKod(config('spkm.coa.susut_nilai'), $masjidId);
        $periodYm = sprintf('%04d-%02d', $tahun, $bulan);
        $tarikh   = Carbon::create($tahun, $bulan, 1)->endOfMonth()->toDateString();

        $senarai = FixedAsset::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('status', 'AKTIF')
            ->orderBy('id')
            ->get();

        $diposkan = 0;
        $dilangkau = 0;
        $jumlah = 0.0;

        foreach ($senarai as $aset) {
            $bulanan = $this->susutBulanan($aset);
            $bakiBoleh = round((float) $aset->kos - (float) $aset->accumulated_depn, 2);

            // Tiada kadar/usia guna, tiada akaun SNT, atau susut sudah penuh — langkau
            if ($bulanan <= 0 || $bakiBoleh <= 0 || !$aset->snt_coa_id) {
                $dilangkau++;
                continue;
            }

            $amaun = min($bulanan, $bakiBoleh); // jangan lebihi kos

            // Idempoten — satu jadual posted per (aset, tahun, bulan)
            $sudah = DepreciationSchedule::query()
                ->where('fixed_asset_id', $aset->id)
                ->where('tahun', $tahun)
                ->where('bulan', $bulan)
                ->where('posted', 1)
                ->exists();
            if ($sudah) {
                $dilangkau++;
                continue;
            }

            DB::transaction(function () use ($aset, $tahun, $bulan, $amaun, $coaSusut, $periodYm, $tarikh, $masjidId) {
                $jadual = DepreciationSchedule::firstOrCreate(
                    ['fixed_asset_id' => $aset->id, 'tahun' => $tahun, 'bulan' => $bulan],
                    ['amaun' => $amaun, 'posted' => 0],
                );
                if ($jadual->posted) {
                    return; // perlumbaan serentak — sudah diposkan
                }

                $voucher = $this->journal->post(
                    tarikh: $tarikh,
                    sourceType: SourceType::JURNAL,
                    sourceId: $aset->id,
                    deskripsi: "SUSUT NILAI {$periodYm}: {$aset->nama} ({$aset->kod_aset})",
                    lines: [
                        ['coa_id' => $coaSusut->id, 'debit' => $amaun, 'kredit' => 0, 'memo' => 'Susut nilai '.$aset->kod_aset],
                        ['coa_id' => $aset->snt_coa_id, 'debit' => 0, 'kredit' => $amaun, 'memo' => 'SNT '.$aset->kod_aset],
                    ],
                    voucherRef: $this->seq->next(SequenceType::JNL, $masjidId),
                    periodYm: $periodYm,
                    masjidId: $masjidId,
                );

                $jadual->update(['amaun' => number_format($amaun, 2, '.', ''), 'posted' => 1, 'voucher_id' => $voucher->id]);

                // C2 — kemas kini ATOM (elak lost-update bila dua bulan diposkan serentak).
                // increment() = `SET accumulated_depn = accumulated_depn + ?` di peringkat SQL.
                FixedAsset::withoutMasjidScope()->whereKey($aset->id)
                    ->increment('accumulated_depn', $amaun);

                $this->audit->log('CREATE', 'depreciation_schedule', null, [
                    'aset' => $aset->kod_aset, 'period' => $periodYm, 'amaun' => number_format($amaun, 2, '.', ''),
                ], $jadual->id, masjidId: $masjidId);
            });

            $diposkan++;
            $jumlah = round($jumlah + $amaun, 2);
        }

        return [
            'diposkan'  => $diposkan,
            'dilangkau' => $dilangkau,
            'jumlah'    => number_format($jumlah, 2, '.', ''),
            'period_ym' => $periodYm,
        ];
    }

    /**
     * Pelupusan aset — keluarkan kos penuh & SNT terkumpul daripada buku;
     * baki nilai buku dicaj ke 600-99990 LAIN-LAIN BELANJA.
     */
    public function lupus(FixedAsset $aset, string $sebab, ?string $tarikh = null, ?int $masjidId = null): JournalVoucher
    {
        $masjidId ??= app('current.masjid_id');
        $tarikh ??= now()->toDateString();

        if ($aset->status !== 'AKTIF') {
            throw new InvalidArgumentException("Aset {$aset->kod_aset} bukan AKTIF — tidak boleh dilupuskan.");
        }

        $kos = round((float) $aset->kos, 2);
        if ($kos <= 0) {
            throw new InvalidArgumentException('Kos aset mesti lebih daripada sifar untuk pelupusan berjurnal.');
        }

        $accum = min(round((float) $aset->accumulated_depn, 2), $kos);
        $bakiBuku = round($kos - $accum, 2);

        if ($accum > 0 && !$aset->snt_coa_id) {
            throw new InvalidArgumentException("Aset {$aset->kod_aset} ada susut terkumpul tetapi tiada akaun SNT.");
        }

        $lines = [];
        if ($accum > 0) {
            $lines[] = ['coa_id' => $aset->snt_coa_id, 'debit' => $accum, 'kredit' => 0, 'memo' => 'Keluarkan SNT '.$aset->kod_aset];
        }
        if ($bakiBuku > 0) {
            $coaLain = $this->journal->coaByKod('600-99990', $masjidId);
            $lines[] = ['coa_id' => $coaLain->id, 'debit' => $bakiBuku, 'kredit' => 0, 'memo' => 'Nilai buku pelupusan '.$aset->kod_aset];
        }
        $lines[] = ['coa_id' => $aset->coa_id, 'debit' => 0, 'kredit' => $kos, 'memo' => 'Pelupusan kos '.$aset->kod_aset];

        return DB::transaction(function () use ($aset, $sebab, $tarikh, $lines, $masjidId) {
            // C3 — kunci baris aset & semak status SEMULA dalam transaksi supaya klik
            // "Lupus" dua kali (serentak/berturut) tidak mencipta DUA voucher pelupusan.
            $segar = FixedAsset::withoutMasjidScope()->whereKey($aset->id)->lockForUpdate()->firstOrFail();
            if ($segar->status !== 'AKTIF') {
                throw new InvalidArgumentException("Aset {$aset->kod_aset} bukan AKTIF — tidak boleh dilupuskan.");
            }

            $voucher = $this->journal->post(
                tarikh: $tarikh,
                sourceType: SourceType::PELUPUSAN,
                sourceId: $aset->id,
                deskripsi: mb_substr("PELUPUSAN ASET: {$aset->nama} ({$aset->kod_aset}) — {$sebab}", 0, 500),
                lines: $lines,
                voucherRef: $this->seq->next(SequenceType::JNL, $masjidId),
                masjidId: $masjidId,
            );

            FixedAsset::withoutMasjidScope()->whereKey($aset->id)->update(['status' => 'DILUPUSKAN']);

            $this->audit->log('UPDATE', 'fixed_asset',
                ['status' => 'AKTIF'],
                ['status' => 'DILUPUSKAN', 'sebab' => mb_substr($sebab, 0, 200), 'voucher' => $voucher->voucher_ref],
                $aset->id, masjidId: $masjidId);

            return $voucher;
        });
    }
}

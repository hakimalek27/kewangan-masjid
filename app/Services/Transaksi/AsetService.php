<?php

namespace App\Services\Transaksi;

use App\Models\Coa;
use App\Models\FixedAsset;
use App\Services\Security\AuditTrailService;

/**
 * Daftar Aset Tetap. Aset belian baharu didaftar oleh PembayaranService
 * (jurnal + daftar serentak); aset lama (opening) didaftar tanpa GL —
 * nilainya masuk melalui Baki Awal.
 */
class AsetService
{
    public function __construct(private AuditTrailService $audit)
    {
    }

    public function register(array $data): FixedAsset
    {
        $aset = FixedAsset::create([
            'kod_aset'          => $data['kod_aset'] ?? $this->janaKodAset(),
            'nama'              => $data['nama'],
            'coa_id'            => $data['coa_id'],
            'snt_coa_id'        => $data['snt_coa_id'] ?? $this->sntUntukCoa($data['coa_id']),
            'tarikh_perolehan'  => $data['tarikh_perolehan'],
            'kos'               => $data['kos'],
            'accumulated_depn'  => $data['accumulated_depn'] ?? 0,
            'useful_life_years' => $data['useful_life_years'] ?? null,
            'depn_rate_pct'     => $data['depn_rate_pct'] ?? null,
            'lokasi'            => $data['lokasi'] ?? null,
            'acquisition_type'  => $data['acquisition_type'] ?? 'PEMBELIAN',
            'donation_value'    => $data['donation_value'] ?? null,
            'jenis_aset'        => $data['jenis_aset'] ?? 'ALIH',
            'tagging_status'    => $data['tagging_status'] ?? 'NOT_TAGGED',
            'status'            => 'AKTIF',
            'pembayaran_id'     => $data['pembayaran_id'] ?? null,
        ]);

        $this->audit->log('CREATE', 'fixed_asset', null, ['kod' => $aset->kod_aset, 'nama' => $aset->nama], $aset->id);

        return $aset;
    }

    /** Daftar Aset Lama (opening balance) — daftar sahaja, TIADA jurnal. */
    public function registerOpening(array $data): FixedAsset
    {
        return $this->register([...$data, 'acquisition_type' => 'BELIAN_LAMA']);
    }

    /** Kod aset unik corak SPPKMS: 'A' + nombor (cth A1781170006). */
    private function janaKodAset(): string
    {
        do {
            $kod = 'A'.now()->format('ymdHis').rand(10, 99);
        } while (FixedAsset::withoutMasjidScope()->where('kod_aset', $kod)->exists());

        return $kod;
    }

    /** SNT (kontra) bagi COA aset: 200-01030 → 200-01035 (corak digit akhir +5). */
    private function sntUntukCoa(int $coaId): ?int
    {
        $coa = Coa::withoutMasjidScope()->find($coaId);
        if (!$coa) {
            return null;
        }

        $kodSnt = substr($coa->kod, 0, -1).'5';

        return Coa::withoutMasjidScope()
            ->where('masjid_id', $coa->masjid_id)
            ->where('kod', $kodSnt)
            ->value('id');
    }
}

<?php

namespace App\Services\Transaksi;

use App\Enums\SequenceType;
use App\Enums\SourceType;
use App\Models\BankAccount;
use App\Models\FixedAsset;
use App\Models\Pembayaran;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\NumberSequenceService;
use App\Services\Accounting\VoidService;
use App\Services\Security\AuditTrailService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pembayaran — matriks Dr/Cr (disahkan empirik):
 *   Bayaran (Cek/EFT)  : Dr 600-xxx Belanja / Cr Bank          → P&L naik, tunai keluar
 *   Bayaran via PWR    : Dr 600-xxx Belanja / Cr 250-060x0 PWR → P&L naik
 *   Beli Aset          : Dr 200-01xxx Aset  / Cr Bank/PWR      → BUKAN P&L (aset, bukan belanja)
 *   Rekupmen PWR/TBK   : Dr 250-060x0 PWR   / Cr Bank          → PEMINDAHAN, bukan belanja
 */
class PembayaranService
{
    public function __construct(
        private JournalService $journal,
        private NumberSequenceService $seq,
        private VoidService $void,
        private AuditTrailService $audit,
        private AsetService $aset,
    ) {
    }

    /** Bayaran perbelanjaan biasa (belanja_expense.php / mode=pwr). */
    public function createBayaran(array $data): Pembayaran
    {
        return DB::transaction(function () use ($data) {
            [$crCoaId, $pwrCoaId, $bankAccountId] = $this->tentukanCrCoa($data);

            $siri = ($data['cara_bayar'] === 'PWR') ? SequenceType::PWR : SequenceType::PV;
            $baucerNo = !empty($data['auto_baucer'])
                ? $this->seq->next($siri)
                : ($data['baucer_no'] ?? throw new InvalidArgumentException('No baucer manual diperlukan.'));

            $pembayaran = $this->simpanRekod($data, 'BAYARAN', $baucerNo, $pwrCoaId, $bankAccountId);

            $voucher = $this->journal->post(
                tarikh: $data['tar_lulus'],
                sourceType: SourceType::BAYARAN,
                sourceId: $pembayaran->id,
                deskripsi: 'BAYARAN: '.($data['deskripsi'] ?? $data['pemohon'] ?? $baucerNo),
                lines: [
                    ['coa_id' => $data['coa_id'], 'debit' => $data['jumlah'], 'kredit' => 0, 'memo' => $baucerNo],
                    ['coa_id' => $crCoaId, 'debit' => 0, 'kredit' => $data['jumlah'], 'memo' => $baucerNo],
                ],
                voucherRef: 'B'.$baucerNo.'-'.$pembayaran->id,
                periodYm: $data['period_ym'] ?? null,
            );

            $pembayaran->update(['voucher_id' => $voucher->id]);
            $this->audit->log('CREATE', 'pembayaran', null, ['baucer' => $baucerNo, 'jumlah' => (string) $data['jumlah']], $pembayaran->id);

            return $pembayaran->fresh();
        });
    }

    /** Pembelian aset (belanja_asset.php) — bayar + daftar aset DALAM SATU transaksi. */
    public function createAset(array $data): Pembayaran
    {
        return DB::transaction(function () use ($data) {
            [$crCoaId, $pwrCoaId, $bankAccountId] = $this->tentukanCrCoa($data);

            $baucerNo = !empty($data['auto_baucer'])
                ? $this->seq->next(SequenceType::PV)
                : ($data['baucer_no'] ?? throw new InvalidArgumentException('No baucer manual diperlukan.'));

            $pembayaran = $this->simpanRekod($data, 'ASET', $baucerNo, $pwrCoaId, $bankAccountId);

            $voucher = $this->journal->post(
                tarikh: $data['tar_lulus'],
                sourceType: SourceType::ASET,
                sourceId: $pembayaran->id,
                deskripsi: 'BELI ASET: '.($data['asset_name'] ?? $data['deskripsi'] ?? $baucerNo),
                lines: [
                    ['coa_id' => $data['coa_id'], 'debit' => $data['jumlah'], 'kredit' => 0, 'memo' => $data['asset_name'] ?? ''],
                    ['coa_id' => $crCoaId, 'debit' => 0, 'kredit' => $data['jumlah'], 'memo' => $baucerNo],
                ],
                voucherRef: 'B'.$baucerNo.'-'.$pembayaran->id,
                periodYm: $data['period_ym'] ?? null,
            );

            $pembayaran->update(['voucher_id' => $voucher->id]);

            $this->aset->register([
                'nama'              => $data['asset_name'],
                'coa_id'            => $data['coa_id'],
                'tarikh_perolehan'  => $data['tar_lulus'],
                'kos'               => $data['jumlah'],
                'useful_life_years' => $data['useful_life'] ?? null,
                'depn_rate_pct'     => $data['depn_rate'] ?? null,
                'lokasi'            => $data['asset_location'] ?? null,
                'acquisition_type'  => 'PEMBELIAN',
                'pembayaran_id'     => $pembayaran->id,
            ]);

            $this->audit->log('CREATE', 'pembayaran', null, ['baucer' => $baucerNo, 'aset' => $data['asset_name']], $pembayaran->id);

            return $pembayaran->fresh();
        });
    }

    /**
     * Rekupmen PWR/TBK — PEMINDAHAN dalaman (Dr PWR / Cr Bank).
     * TIADA kesan P&L (disahkan empirik + keputusan pengguna semasa migrasi).
     */
    public function createRekupmen(array $data): Pembayaran
    {
        return DB::transaction(function () use ($data) {
            $bank = BankAccount::withoutMasjidScope()
                ->where('masjid_id', app()->bound('current.masjid_id') ? (int) app('current.masjid_id') : (int) config('sppkms.masjid_id'))
                ->findOrFail($data['bank_account_id']);

            $baucerNo = !empty($data['auto_baucer'])
                ? $this->seq->next(SequenceType::PWR)
                : ($data['baucer_no'] ?? throw new InvalidArgumentException('No baucer manual diperlukan.'));

            $pembayaran = $this->simpanRekod(
                [...$data, 'coa_id' => $data['pwr_coa_id'], 'cara_bayar' => $data['cara_bayar'] ?? 'EFT'],
                'REKUPMEN', $baucerNo, $data['pwr_coa_id'], $bank->id
            );

            $voucher = $this->journal->post(
                tarikh: $data['tar_lulus'],
                sourceType: SourceType::REKUPMEN,
                sourceId: $pembayaran->id,
                deskripsi: 'REKUPMEN PWR: '.($data['deskripsi'] ?? $baucerNo),
                lines: [
                    ['coa_id' => $data['pwr_coa_id'], 'debit' => $data['jumlah'], 'kredit' => 0, 'memo' => $baucerNo],
                    ['coa_id' => $bank->coa_id, 'debit' => 0, 'kredit' => $data['jumlah'], 'memo' => $baucerNo],
                ],
                voucherRef: 'B'.$baucerNo.'-'.$pembayaran->id,
                periodYm: $data['period_ym'] ?? null,
            );

            $pembayaran->update(['voucher_id' => $voucher->id]);
            $this->audit->log('CREATE', 'pembayaran', null, ['baucer' => $baucerNo, 'jenis' => 'REKUPMEN'], $pembayaran->id);

            return $pembayaran->fresh();
        });
    }

    /**
     * "Padam/Batal" bayaran = VOID voucher + status CANCELLED.
     * Bayaran ASET → aset turut ditanda DIPADAM (membaiki quirk sistem lama
     * yang meninggalkan baris aset yatim).
     */
    public function void(Pembayaran $pembayaran, string $sebab = ''): void
    {
        DB::transaction(function () use ($pembayaran, $sebab) {
            if ($pembayaran->voucher_id) {
                $this->void->voidVoucher($pembayaran->voucher()->first(), $sebab);
            }
            $pembayaran->update(['status' => 'CANCELLED']);

            if ($pembayaran->jenis === 'ASET') {
                FixedAsset::withoutMasjidScope()
                    ->where('pembayaran_id', $pembayaran->id)
                    ->update(['status' => 'DIPADAM']);
            }

            $this->audit->log('DELETE', 'pembayaran',
                ['baucer' => $pembayaran->baucer_no, 'jumlah' => (string) $pembayaran->jumlah], null, $pembayaran->id);
        });
    }

    private function simpanRekod(array $data, string $jenis, string $baucerNo, ?int $pwrCoaId, ?int $bankAccountId): Pembayaran
    {
        return Pembayaran::create([
            'jenis'           => $jenis,
            'tar_mohon'       => $data['tar_mohon'] ?? $data['tar_lulus'],
            'tar_lulus'       => $data['tar_lulus'],
            'period_ym'       => $data['period_ym'] ?? substr($data['tar_lulus'], 0, 7),
            'no_baucer'       => $data['no_baucer'] ?? null,
            'baucer_no'       => $baucerNo,
            'pemohon'         => $data['pemohon'] ?? null,
            'nokp'            => $data['nokp'] ?? null,
            'contactno'       => $data['contactno'] ?? null,
            'alamat'          => $data['alamat'] ?? null,
            'coa_id'          => $data['coa_id'],
            'deskripsi'       => $data['deskripsi'] ?? null,
            'program'         => $data['program'] ?? null,
            'butiran'         => $data['butiran'] ?? null,
            'jumlah'          => $data['jumlah'],
            'cara_bayar'      => $data['cara_bayar'],
            'bank_account_id' => $bankAccountId,
            'pwr_coa_id'      => $pwrCoaId,
            'no_cek'          => $data['no_cek'] ?? null,
            'no_acct'         => $data['no_acct'] ?? null,
            'status'          => 'ACTIVE',
            'created_by'      => app()->bound('current.user_id') ? app('current.user_id') : null,
        ]);
    }

    /** @return array{0:int,1:?int,2:?int} [Cr COA id, pwr_coa_id, bank_account_id] */
    private function tentukanCrCoa(array $data): array
    {
        if (($data['cara_bayar'] ?? '') === 'PWR') {
            $pwrCoaId = (int) ($data['pwr_coa_id'] ?? throw new InvalidArgumentException('Akaun PWR diperlukan untuk bayaran PWR.'));

            return [$pwrCoaId, $pwrCoaId, null];
        }

        $bank = BankAccount::withoutMasjidScope()
            ->where('masjid_id', app()->bound('current.masjid_id') ? (int) app('current.masjid_id') : (int) config('sppkms.masjid_id'))
            ->findOrFail($data['bank_account_id'] ?? 0);

        return [(int) $bank->coa_id, null, (int) $bank->id];
    }
}

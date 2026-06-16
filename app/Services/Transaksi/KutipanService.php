<?php

namespace App\Services\Transaksi;

use App\Enums\SequenceType;
use App\Enums\SourceType;
use App\Models\FdDividend;
use App\Models\FdInvestment;
use App\Models\Kutipan;
use App\Models\KutipanDenominasi;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\NumberSequenceService;
use App\Services\Accounting\VoidService;
use App\Services\Security\AuditTrailService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Penerimaan/Kutipan — matriks Dr/Cr (disahkan empirik):
 *   Bank/Cek/QR : Dr 250-050x0 Bank   / Cr COA hasil (400/450) atau liabiliti (300-04xxx)
 *   Tunai       : Dr 250-06000 Tunai  / Cr COA hasil
 *   Dividen FD  : Dr Bank             / Cr 450-xxx
 */
class KutipanService
{
    public function __construct(
        private JournalService $journal,
        private NumberSequenceService $seq,
        private VoidService $void,
        private AuditTrailService $audit,
    ) {
    }

    public function create(array $data): Kutipan
    {
        return DB::transaction(function () use ($data) {
            $auto = (bool) ($data['auto_resit'] ?? false);
            $noResit = $auto
                ? $this->seq->next(SequenceType::RESIT)
                : ($data['no_resit'] ?? throw new InvalidArgumentException('No resit manual diperlukan.'));

            $drCoaId = $this->tentukanDrCoa($data);

            $kutipan = Kutipan::create([
                'jenis'           => $data['jenis'] ?? 'BIASA',
                'tarikh'          => $data['tarikh'],
                'period_ym'       => $data['period_ym'] ?? substr($data['tarikh'], 0, 7),
                'coa_id'          => $data['coa_id'],
                'kaedah'          => $data['kaedah'],
                'jumlah'          => $data['jumlah'],
                'no_resit'        => $noResit,
                'auto_resit'      => $auto,
                'nama_pemberi'    => $data['nama_pemberi'] ?? null,
                'saksi1'          => $data['saksi1'] ?? null,
                'saksi2'          => $data['saksi2'] ?? null,
                'saksi3'          => $data['saksi3'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'no_slip'         => $data['no_slip'] ?? null,
                'tar_bankin'      => $data['tar_bankin'] ?? null,
                'jenis_tabung'    => $data['jenis_tabung'] ?? null,
                'tar_kira'        => $data['tar_kira'] ?? null,
                'dibank_oleh'     => $data['dibank_oleh'] ?? null,
                'fd_id'           => $data['fd_id'] ?? null,
                'deskripsi'       => $data['deskripsi'] ?? null,
                'program'         => $data['program'] ?? null,
                'butiran'         => $data['butiran'] ?? null,
                'status'          => 'ACTIVE',
                'created_by'      => app()->bound('current.user_id') ? app('current.user_id') : null,
            ]);

            $sourceType = ($data['jenis'] ?? 'BIASA') === 'DIVIDEN' ? SourceType::DIVIDEN : SourceType::KUTIPAN;

            $voucher = $this->journal->post(
                tarikh: $data['tarikh'],
                sourceType: $sourceType,
                sourceId: $kutipan->id,
                deskripsi: 'TERIMAAN: '.($data['deskripsi'] ?? $data['nama_pemberi'] ?? 'Resit '.$noResit),
                lines: [
                    ['coa_id' => $drCoaId, 'debit' => $data['jumlah'], 'kredit' => 0, 'memo' => 'Resit '.$noResit],
                    ['coa_id' => $data['coa_id'], 'debit' => 0, 'kredit' => $data['jumlah'], 'memo' => 'Resit '.$noResit],
                ],
                voucherRef: $this->seq->next(SequenceType::VKUTIPAN),
                periodYm: $data['period_ym'] ?? null,
            );

            $kutipan->update(['voucher_id' => $voucher->id]);

            $this->audit->log('CREATE', 'kutipan', null, [
                'resit' => $noResit, 'jumlah' => (string) $data['jumlah'], 'voucher' => $voucher->voucher_ref,
            ], $kutipan->id);

            return $kutipan->fresh();
        });
    }

    /**
     * Kutipan Tabung (Harian/Jumaat) — jumlah dikira daripada denominasi
     * RM100 → 1 sen (11 baris). COA hasil ditentukan oleh jenis tabung.
     *
     * @param array<int, array{denominasi:numeric, bilangan:int}> $denominasi
     */
    public function createTabung(array $data, array $denominasi): Kutipan
    {
        return DB::transaction(function () use ($data, $denominasi) {
            $jumlah = '0.00';
            foreach ($denominasi as $d) {
                $jumlah = bcadd($jumlah, bcmul((string) $d['denominasi'], (string) $d['bilangan'], 2), 2);
            }
            if (bccomp($jumlah, '0', 2) <= 0) {
                throw new InvalidArgumentException('Jumlah denominasi mesti lebih daripada sifar.');
            }

            $kodHasil = ($data['jenis_tabung'] ?? '') === 'JUMAAT' ? '400-01020' : '400-01010';

            $kutipan = $this->create([
                ...$data,
                'jenis'  => 'TABUNG',
                'coa_id' => $this->journal->coaByKod($kodHasil)->id,
                'jumlah' => $jumlah,
            ]);

            KutipanDenominasi::insert(
                collect($denominasi)
                    ->filter(fn ($d) => (int) $d['bilangan'] > 0)
                    ->map(fn ($d) => [
                        'kutipan_id' => $kutipan->id,
                        'denominasi' => $d['denominasi'],
                        'bilangan'   => $d['bilangan'],
                    ])->values()->all()
            );

            return $kutipan;
        });
    }

    /** Terimaan Dividen/Hibah FD — turut mencipta baris fd_dividend terpaut. */
    public function createDividen(FdInvestment $fd, array $data): Kutipan
    {
        return DB::transaction(function () use ($fd, $data) {
            $kutipan = $this->create([
                ...$data,
                'jenis'  => 'DIVIDEN',
                'fd_id'  => $fd->id,
                'kaedah' => $data['kaedah'] ?? 'BANK_TRANSFER_QR',
            ]);

            FdDividend::create([
                'fd_id'          => $fd->id,
                'kutipan_id'     => $kutipan->id,
                'coa_dividen_id' => $data['coa_id'],
                'jumlah'         => $data['jumlah'],
                'tarikh'         => $data['tarikh'],
            ]);

            return $kutipan;
        });
    }

    /** "Padam" kutipan = VOID voucher + tanda rekod DELETED (jejak audit kekal). */
    public function void(Kutipan $kutipan, string $sebab = ''): void
    {
        DB::transaction(function () use ($kutipan, $sebab) {
            if ($kutipan->voucher_id) {
                $this->void->voidVoucher($kutipan->voucher()->first(), $sebab);
            }
            $kutipan->update(['status' => 'DELETED']);

            // Padam dividen → buang pautan fd_dividend juga
            FdDividend::where('kutipan_id', $kutipan->id)->delete();

            $this->audit->log('DELETE', 'kutipan', ['resit' => $kutipan->no_resit, 'jumlah' => (string) $kutipan->jumlah], null, $kutipan->id);
        });
    }

    /** Tentukan akaun Debit: TUNAI → 250-06000; selainnya → COA bank dipilih. */
    private function tentukanDrCoa(array $data): int
    {
        if (($data['kaedah'] ?? '') === 'TUNAI' && empty($data['bank_account_id'])) {
            return $this->journal->coaByKod(config('sppkms.coa.tunai_di_tangan'))->id;
        }

        $bank = \App\Models\BankAccount::withoutMasjidScope()->findOrFail($data['bank_account_id'] ?? 0);

        return (int) $bank->coa_id;
    }
}

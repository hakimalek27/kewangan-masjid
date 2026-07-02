<?php

namespace App\Services\Lanjutan;

use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Services\Security\AuditTrailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Rekonsiliasi Bank (Fasa 9).
 *   1. Import CSV penyata bank → bank_statement_line (UNMATCHED).
 *      Lajur: tarikh [Y-m-d atau d/m/Y], deskripsi, debit, kredit, baki.
 *      Baris pertama = header (dikesan automatik — tarikh tidak boleh diurai).
 *   2. Padanan AUTO: journal_entry pada COA bank (voucher POSTED), amaun sama,
 *      tarikh ±3 hari, voucher belum dipadan → MATCHED + matched_voucher_id.
 *      Wang masuk penyata (kredit) = Dr bank di buku; wang keluar (debit) = Cr bank.
 *   3. Padanan manual + laporan ringkas (penyata vs buku + beza).
 */
class ReconciliationService
{
    public function __construct(private AuditTrailService $audit)
    {
    }

    /** Import kandungan CSV. Pulangkan ringkasan [diimport, matched, unmatched]. */
    public function importCsv(int $bankAccountId, string $kandungan, ?int $masjidId = null): array
    {
        $masjidId ??= app('current.masjid_id');

        $bank = BankAccount::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->findOrFail($bankAccountId);

        $baris = preg_split('/\r\n|\r|\n/', trim($kandungan));
        $diimport = 0;

        DB::transaction(function () use ($baris, $bank, &$diimport) {
            foreach ($baris as $i => $line) {
                if (trim($line) === '') {
                    continue;
                }

                $sel = str_getcsv($line, str_contains($line, ';') && !str_contains($line, ',') ? ';' : ',');
                if (count($sel) < 3) {
                    continue;
                }

                $tarikh = $this->uraiTarikh(trim((string) ($sel[0] ?? '')));
                if ($tarikh === null) {
                    // Baris header (atau tarikh rosak) — kesan automatik & langkau
                    continue;
                }

                $debit  = $this->uraiAmaun($sel[2] ?? '0');
                $kredit = $this->uraiAmaun($sel[3] ?? '0');
                if ($debit <= 0 && $kredit <= 0) {
                    continue;
                }

                $deskripsi = mb_substr(trim((string) ($sel[1] ?? '')), 0, 255);

                // E8 — dedup: langkau baris SERUPA yang sudah wujud (elak gandaan bila
                // fail CSV sama diimport dua kali). Padanan: bank + tarikh + debit + kredit + deskripsi.
                $wujud = BankStatementLine::where('bank_account_id', $bank->id)
                    ->where('tarikh', $tarikh)
                    ->where('debit', number_format($debit, 2, '.', ''))
                    ->where('kredit', number_format($kredit, 2, '.', ''))
                    ->where('deskripsi', $deskripsi)
                    ->exists();
                if ($wujud) {
                    continue;
                }

                BankStatementLine::create([
                    'bank_account_id' => $bank->id,
                    'tarikh'          => $tarikh,
                    'deskripsi'       => $deskripsi,
                    'debit'           => number_format($debit, 2, '.', ''),
                    'kredit'          => number_format($kredit, 2, '.', ''),
                    'baki'            => isset($sel[4]) && trim((string) $sel[4]) !== '' ? number_format($this->uraiAmaun($sel[4]), 2, '.', '') : null,
                    'status'          => 'UNMATCHED',
                ]);
                $diimport++;
            }
        });

        if ($diimport === 0) {
            throw new InvalidArgumentException('Tiada baris penyata sah dalam fail CSV (semak format lajur: tarikh, deskripsi, debit, kredit, baki).');
        }

        $dipadan = $this->padanAuto($bank->id, $masjidId);

        $this->audit->log('CREATE', 'bank_statement_line', null, [
            'bank' => $bank->nama_bank, 'diimport' => $diimport, 'auto_match' => $dipadan,
        ], null, masjidId: $masjidId);

        return [
            'diimport' => $diimport,
            'matched'  => $dipadan,
            'unmatched' => $diimport - $dipadan,
        ];
    }

    /** Padanan automatik semua baris UNMATCHED bank ini. Pulangkan bilangan dipadan. */
    public function padanAuto(int $bankAccountId, ?int $masjidId = null): int
    {
        $masjidId ??= app('current.masjid_id');

        $bank = BankAccount::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->findOrFail($bankAccountId);

        $dipadan = 0;

        $senarai = BankStatementLine::query()
            ->where('bank_account_id', $bank->id)
            ->where('status', 'UNMATCHED')
            ->orderBy('tarikh')->orderBy('id')
            ->get();

        foreach ($senarai as $line) {
            $masuk = (float) $line->kredit > 0; // wang masuk penyata = Dr bank di buku
            $amaun = number_format($masuk ? (float) $line->kredit : (float) $line->debit, 2, '.', '');
            $tarikh = $line->tarikh instanceof \DateTimeInterface ? $line->tarikh->format('Y-m-d') : (string) $line->tarikh;

            $calon = DB::table('journal_entry as je')
                ->join('journal_voucher as jv', 'jv.id', '=', 'je.voucher_id')
                ->where('jv.status', 'POSTED')
                ->where('jv.masjid_id', $masjidId)
                ->where('je.coa_id', $bank->coa_id)
                ->where($masuk ? 'je.debit' : 'je.kredit', $amaun)
                ->whereBetween('jv.tarikh', [
                    Carbon::parse($tarikh)->subDays(3)->toDateString(),
                    Carbon::parse($tarikh)->addDays(3)->toDateString(),
                ])
                ->whereNotIn('jv.id', fn ($q) => $q->select('matched_voucher_id')
                    ->from('bank_statement_line')
                    ->whereNotNull('matched_voucher_id'))
                ->orderByRaw('ABS(DATEDIFF(jv.tarikh, ?))', [$tarikh])
                ->orderBy('jv.id')
                ->value('jv.id');

            if ($calon) {
                $line->update(['status' => 'MATCHED', 'matched_voucher_id' => $calon]);
                $dipadan++;
            }
        }

        return $dipadan;
    }

    /** Padanan manual satu baris penyata dengan satu voucher. */
    public function padanManual(BankStatementLine $line, int $voucherId, ?int $masjidId = null): void
    {
        $masjidId ??= app('current.masjid_id');

        if ($line->status === 'MATCHED') {
            throw new InvalidArgumentException('Baris penyata ini telah pun dipadankan.');
        }

        $wujud = DB::table('journal_voucher')
            ->where('id', $voucherId)
            ->where('masjid_id', $masjidId)
            ->where('status', 'POSTED')
            ->exists();
        if (!$wujud) {
            throw new InvalidArgumentException('Voucher tidak sah atau bukan POSTED.');
        }

        $sudahGuna = BankStatementLine::query()
            ->where('matched_voucher_id', $voucherId)
            ->where('id', '!=', $line->id)
            ->exists();
        if ($sudahGuna) {
            throw new InvalidArgumentException('Voucher ini telah dipadankan dengan baris penyata lain.');
        }

        $line->update(['status' => 'MATCHED', 'matched_voucher_id' => $voucherId]);

        $this->audit->log('UPDATE', 'bank_statement_line',
            ['status' => 'UNMATCHED'], ['status' => 'MATCHED', 'voucher_id' => $voucherId],
            $line->id, masjidId: $masjidId);
    }

    /** Buang padanan / kembalikan baris kepada UNMATCHED (atau IGNORED). */
    public function setStatus(BankStatementLine $line, string $status): void
    {
        if (!in_array($status, ['UNMATCHED', 'IGNORED'], true)) {
            throw new InvalidArgumentException('Status tidak sah.');
        }

        $line->update(['status' => $status, 'matched_voucher_id' => null]);
    }

    /** Laporan ringkas: jumlah penyata vs jumlah buku (COA bank) + beza, ikut julat penyata. */
    public function laporan(int $bankAccountId, ?int $masjidId = null): array
    {
        $masjidId ??= app('current.masjid_id');

        $bank = BankAccount::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->findOrFail($bankAccountId);

        $julat = BankStatementLine::query()
            ->where('bank_account_id', $bank->id)
            ->selectRaw('MIN(tarikh) as dari, MAX(tarikh) as hingga, COUNT(*) as bil,
                ROUND(SUM(kredit - debit),2) as bersih_penyata,
                SUM(status = "MATCHED") as bil_matched,
                SUM(status = "UNMATCHED") as bil_unmatched')
            ->first();

        $bersihBuku = 0.0;
        if ($julat && $julat->dari) {
            $bersihBuku = (float) DB::table('journal_entry as je')
                ->join('journal_voucher as jv', 'jv.id', '=', 'je.voucher_id')
                ->where('jv.status', 'POSTED')
                ->where('jv.masjid_id', $masjidId)
                ->where('je.coa_id', $bank->coa_id)
                ->whereBetween('jv.tarikh', [$julat->dari, $julat->hingga])
                ->selectRaw('COALESCE(SUM(je.debit - je.kredit),0) as b')
                ->value('b');
        }

        $bersihPenyata = (float) ($julat->bersih_penyata ?? 0);

        return [
            'dari'           => $julat->dari ?? null,
            'hingga'         => $julat->hingga ?? null,
            'bil'            => (int) ($julat->bil ?? 0),
            'bil_matched'    => (int) ($julat->bil_matched ?? 0),
            'bil_unmatched'  => (int) ($julat->bil_unmatched ?? 0),
            'bersih_penyata' => number_format($bersihPenyata, 2, '.', ''),
            'bersih_buku'    => number_format($bersihBuku, 2, '.', ''),
            'beza'           => number_format($bersihPenyata - $bersihBuku, 2, '.', ''),
        ];
    }

    /** Tarikh 'Y-m-d' atau 'd/m/Y' (juga 'd-m-Y'). null = bukan tarikh (header). */
    private function uraiTarikh(string $nilai): ?string
    {
        if ($nilai === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai)) {
            return checkdate((int) substr($nilai, 5, 2), (int) substr($nilai, 8, 2), (int) substr($nilai, 0, 4)) ? $nilai : null;
        }

        if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $nilai, $m)) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3])
                ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1])
                : null;
        }

        return null;
    }

    /** Amaun: buang 'RM', koma ribu & ruang. */
    private function uraiAmaun(mixed $nilai): float
    {
        $bersih = preg_replace('/[^0-9.\-]/', '', (string) $nilai);

        return $bersih === '' || $bersih === '-' ? 0.0 : round((float) $bersih, 2);
    }
}

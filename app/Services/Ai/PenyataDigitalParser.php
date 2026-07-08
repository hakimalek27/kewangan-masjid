<?php

namespace App\Services\Ai;

use App\Ai\DTO\StatementLine;

/**
 * Parser penyata bank DIGITAL (teks terbenam) secara DETERMINISTIK — TIADA AI.
 *
 * Untuk penyata digital berbentuk JADUAL, menggunakan AI (LLM) untuk transkrip
 * setiap baris adalah SALAH: mahal (token output banyak), lambat, dan tidak
 * konsisten (dua kali scan beri hasil berbeza + baris tertinggal). Teks jadual
 * sepatutnya dibaca terus — percuma, serta-merta, dan 100% konsisten.
 *
 * Disokong: format "Account Report" Affin/AffinMax (lajur Transaction Date …
 * Debit Credit Balance) yang dijana pdftotext -layout. Format lain → null
 * (pemanggil jatuh ke laluan AI).
 */
class PenyataDigitalParser
{
    /**
     * @return array{lines: array<StatementLine>, grand_debit: ?float, grand_credit: ?float, closing: ?float}|null
     */
    public function cubaParse(string $teks): ?array
    {
        $baris = preg_split('/\r?\n/', $teks) ?: [];

        // Jumlah TERCETAK dalam penyata — untuk checksum tally.
        $grandDebit = $this->cariAngka($teks, '/Grand\s+Total\s+Debit\s*:?\s*([\d,]+\.\d{2})/i');
        $grandCredit = $this->cariAngka($teks, '/Grand\s+Total\s+Credit\s*:?\s*([\d,]+\.\d{2})/i');
        $closing = $this->cariAngka($teks, '/Closing\s+Balance\s*:?\s*([\d,]+\.\d{2})/i');

        $lines = [];
        $blok = null;
        $blokKol = null;
        $kolSemasa = null;
        $formatOk = false;

        // pdftotext -layout melaras jarak lajur SETIAP MUKA → kesan semula kedudukan
        // lajur pada setiap baris header (bukan sekali sahaja) supaya slice tepat.
        foreach ($baris as $ln) {
            $k = $this->kesanLajurBaris($ln);
            if ($k !== null) {
                if ($blok !== null && ($l = $this->parseBlok($blok, $blokKol))) {
                    $lines[] = $l; // tutup blok muka sebelum
                }
                $blok = null;
                $kolSemasa = $k;
                $formatOk = true;

                continue;
            }
            if ($this->barisBunyi($ln)) {
                continue; // kepala/kaki muka
            }
            if ($kolSemasa !== null && preg_match('/^\s*\d{2}\/\d{2}\/\d{4}\b/', $ln)) {
                if ($blok !== null && ($l = $this->parseBlok($blok, $blokKol))) {
                    $lines[] = $l;
                }
                $blok = [$ln];
                $blokKol = $kolSemasa;
            } elseif ($blok !== null && trim($ln) !== '') {
                $blok[] = $ln; // sambungan (nama/deskripsi berbilang baris)
            }
        }
        if ($blok !== null && ($l = $this->parseBlok($blok, $blokKol))) {
            $lines[] = $l;
        }

        if (!$formatOk || empty($lines)) {
            return null;
        }

        return ['lines' => $lines, 'grand_debit' => $grandDebit, 'grand_credit' => $grandCredit, 'closing' => $closing];
    }

    /**
     * Jika $ln ialah baris HEADER lajur (Affin), pulang julat [mula,tamat) lajur
     * deskriptif untuk muka itu; jika bukan header → null.
     *
     * @return array{desc: array{int,int}, name: array{int,int}, other: array{int,int}}|null
     */
    private function kesanLajurBaris(string $ln): ?array
    {
        if (stripos($ln, 'Description') === false || stripos($ln, 'Debit') === false
            || stripos($ln, 'Credit') === false || stripos($ln, 'Balance') === false
            || stripos($ln, 'Currency') === false || stripos($ln, 'Name') === false) {
            return null;
        }

        $pDesc = stripos($ln, 'Description');
        $pCheque = stripos($ln, 'Cheque');
        $pName = stripos($ln, 'Name');
        $pOther = stripos($ln, 'Other');
        $pRemarks = stripos($ln, 'Remarks');
        $pTxnRef = $this->posSelepas($ln, 'Transaction', (int) $pName); // Ref = Transaction selepas Name

        if ($pDesc === false || $pCheque === false || $pName === false
            || $pOther === false || $pRemarks === false || $pTxnRef === null) {
            return null;
        }

        // Remarks SENGAJA tidak digubah (biasa kosong + berisiko tangkap "MYR").
        return [
            'desc' => [(int) $pDesc, (int) $pCheque],
            'name' => [(int) $pName, $pTxnRef],
            'other' => [(int) $pOther, (int) $pRemarks],
        ];
    }

    /** Offset pertama $jarum yang bermula selepas $mula, atau null. */
    private function posSelepas(string $teks, string $jarum, int $mula): ?int
    {
        $p = stripos($teks, $jarum, $mula + 1);

        return $p === false ? null : $p;
    }

    /**
     * Parse satu blok baris (baris tarikh + sambungan) → satu StatementLine.
     * Amaun (Debit/Credit/Balance) dari HUJUNG baris tarikh; deskripsi dihimpun
     * dari lajur Description+Name+Other+Remarks merentas semua baris blok.
     */
    private function parseBlok(array $blok, array $kol): ?StatementLine
    {
        $utama = $blok[0];

        if (!preg_match('/^\s*(\d{2})\/(\d{2})\/(\d{4})\b/', $utama, $t)) {
            return null;
        }
        $tarikh = $t[3].'-'.$t[2].'-'.$t[1];

        // "… MYR <debit> <credit> <balance>" — satu daripada debit/credit ialah "-".
        if (!preg_match('/\bMYR\b\s+(\S+)\s+(\S+)\s+([\d,]+\.\d{2})\s*$/', $utama, $a)) {
            return null; // baki-bawa / subtotal tanpa amaun penuh → langkau
        }
        [$debit, $kredit, $baki] = [$a[1], $a[2], $a[3]];

        $deskripsi = trim(preg_replace('/\s+/', ' ',
            $this->potongLajur($blok, $kol['desc']).' '
            .$this->potongLajur($blok, $kol['name']).' '
            .$this->potongLajur($blok, $kol['other'])
        ) ?? '');

        $line = StatementLine::fromArray([
            'tarikh' => $tarikh,
            'deskripsi' => $deskripsi !== '' ? $deskripsi : 'Transaksi',
            'debit' => $debit === '-' ? '0' : $debit,
            'kredit' => $kredit === '-' ? '0' : $kredit,
            'baki' => $baki,
            // Data ekstraksi PASTI tepat (deterministik), tetapi ini ialah keyakinan
            // cadangan COA — bendahari tetap sahkan kod akaun. 50 = "sila semak".
            'confidence' => 50,
        ]);

        // Sah = tepat satu sisi (debit XOR kredit). Baris B/F dua-dua 0 → dibuang.
        return $line->sah() ? $line : null;
    }

    /** Himpun teks satu lajur [mula,tamat) merentas semua baris blok. */
    private function potongLajur(array $blok, array $julat): string
    {
        [$mula, $tamat] = $julat;
        $lebar = max(0, $tamat - $mula);
        $keping = [];
        foreach ($blok as $ln) {
            if (mb_strlen($ln) <= $mula) {
                continue;
            }
            $sekerat = trim(mb_substr($ln, $mula, $lebar));
            if ($sekerat !== '' && $sekerat !== '-') {
                $keping[] = $sekerat;
            }
        }

        return implode(' ', $keping);
    }

    /** Baris kepala/kaki muka (bukan transaksi) yang mesti dilangkau. */
    private function barisBunyi(string $ln): bool
    {
        if (trim($ln) === '') {
            return true;
        }

        return (bool) preg_match(
            '/\b(Account (Number|Name|Type|Currency|Report)|Date (From|To)\b|Grand Total|Total No\.? of Transaction|Opening Balance|Closing Balance|Transaction\s+Date|AFFIN|Page\s*:?\s*\d)/i',
            $ln
        );
    }

    private function cariAngka(string $teks, string $corak): ?float
    {
        if (preg_match($corak, $teks, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }

        return null;
    }
}

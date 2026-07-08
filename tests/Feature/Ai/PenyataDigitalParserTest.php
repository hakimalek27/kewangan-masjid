<?php

namespace Tests\Feature\Ai;

use App\Services\Ai\PenyataDigitalParser;
use Tests\TestCase;

/**
 * Parser DETERMINISTIK penyata digital (format jadual Affin) — TIADA AI.
 * Membuktikan ekstraksi tepat + konsisten + checksum tally terhadap Grand Total.
 */
class PenyataDigitalParserTest extends TestCase
{
    /** Bina satu baris dengan teks pada kedudukan lajur TEPAT (selaras header). */
    private function baris(array $kolTeks): string
    {
        $s = '';
        foreach ($kolTeks as $pos => $teks) {
            if (strlen($s) < $pos) {
                $s .= str_repeat(' ', $pos - strlen($s));
            }
            $s .= $teks;
        }

        return $s;
    }

    /** Penyata Affin sintetik: 2 kredit + 1 debit (deskripsi berbilang baris). */
    private function penyataUji(): string
    {
        $H = fn ($k) => $this->baris($k);
        $lines = [
            '                                        Account Report',
            'Account Number             : 105040001582',
            'Grand Total Debit          : 50.00              Grand Total Credit   : 15.00',
            'Total No. of Transaction   : 3',
            'Opening Balance            : 1,000.00           Closing Balance      : 965.00',
            '',
            $H([0 => 'Transaction', 14 => 'Transaction', 28 => 'Branch', 40 => 'Description', 54 => 'Cheque', 63 => 'Name', 77 => 'Transaction', 92 => 'Recipient', 105 => 'Other', 118 => 'Remarks', 128 => 'Currency', 139 => 'Debit', 152 => 'Credit', 163 => 'Balance']),
            $H([0 => 'Date', 14 => 'Time', 28 => 'Name', 54 => 'No.', 77 => 'Ref', 92 => 'Ref No', 105 => 'Payment']),
            $H([0 => '28/02/2025', 14 => '23:40', 28 => 'PAYMENT', 40 => 'DuitNow QR', 63 => 'FIQAR FARM', 77 => '20250228MB', 92 => '75700131', 105 => 'DuitQR Mcht', 128 => 'MYR', 139 => '-', 152 => '10.00', 163 => '1,010.00']),
            $H([28 => 'CENTRE', 40 => 'Credit', 63 => 'HEAVEN', 77 => 'BEMYKL0307', 105 => 'Transfer']),
            $H([0 => '28/02/2025', 14 => '21:07', 28 => 'PAYMENT', 40 => 'DuitNow QR', 63 => 'ABD LATIF', 77 => '20250228MB', 92 => '84426479', 105 => 'DuitQR Mcht', 128 => 'MYR', 139 => '-', 152 => '5.00', 163 => '1,015.00']),
            $H([28 => 'CENTRE', 40 => 'Credit', 63 => 'BIN MOHAMED', 77 => 'BEMYKL0308', 105 => 'Transfer']),
            $H([0 => '27/02/2025', 14 => '10:26', 28 => 'AI WANGSA', 40 => 'CHEQUE', 63 => '-', 77 => '-', 92 => '-', 105 => '-', 128 => 'MYR', 139 => '50.00', 152 => '-', 163 => '965.00']),
            $H([28 => 'MAJU', 40 => 'PROCESSING']),
            $H([40 => 'FEE']),
        ];

        return implode("\n", $lines);
    }

    public function test_parse_affin_tepat_dan_tally(): void
    {
        $hasil = (new PenyataDigitalParser())->cubaParse($this->penyataUji());

        $this->assertNotNull($hasil);
        $lines = $hasil['lines'];
        $this->assertCount(3, $lines);

        // Jumlah diekstrak TALLY dengan Grand Total tercetak.
        $sumKredit = array_sum(array_map(fn ($l) => (float) $l->kredit, $lines));
        $sumDebit = array_sum(array_map(fn ($l) => (float) $l->debit, $lines));
        $this->assertSame(15.00, round($sumKredit, 2));
        $this->assertSame(50.00, round($sumDebit, 2));
        $this->assertSame(15.00, round((float) $hasil['grand_credit'], 2));
        $this->assertSame(50.00, round((float) $hasil['grand_debit'], 2));
        $this->assertSame(965.00, round((float) $hasil['closing'], 2));

        // Baris 1 = kredit RM10, deskripsi terkumpul berbilang baris (Desc+Name+Other).
        $this->assertSame('2025-02-28', $lines[0]->tarikh);
        $this->assertSame('10.00', $lines[0]->kredit);
        $this->assertSame('0.00', $lines[0]->debit);
        $this->assertStringContainsString('DuitNow QR Credit', $lines[0]->deskripsi);
        $this->assertStringContainsString('FIQAR FARM HEAVEN', $lines[0]->deskripsi);

        // Baris 3 = debit RM50, deskripsi berbilang baris "CHEQUE PROCESSING FEE".
        $this->assertSame('50.00', $lines[2]->debit);
        $this->assertSame('0.00', $lines[2]->kredit);
        $this->assertStringContainsString('CHEQUE PROCESSING FEE', $lines[2]->deskripsi);
    }

    /** Deterministik: dua kali parse fail SAMA → hasil IDENTIK (tidak seperti AI). */
    public function test_parse_deterministik_konsisten(): void
    {
        $parser = new PenyataDigitalParser();
        $a = $parser->cubaParse($this->penyataUji());
        $b = $parser->cubaParse($this->penyataUji());

        $petakan = fn ($h) => array_map(fn ($l) => [$l->tarikh, $l->debit, $l->kredit, $l->deskripsi], $h['lines']);
        $this->assertSame($petakan($a), $petakan($b));
    }

    /** Format tak dikenali (bukan jadual Affin) → null (pemanggil jatuh ke AI). */
    public function test_format_asing_pulang_null(): void
    {
        $teks = "Ini bukan penyata bank.\nHanya teks biasa tanpa lajur transaksi.\n";
        $this->assertNull((new PenyataDigitalParser())->cubaParse($teks));
    }
}

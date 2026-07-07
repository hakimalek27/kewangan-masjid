<?php

namespace Tests\Feature\Laporan;

use App\Exports\JadualExport;
use App\Models\AppUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Eksport laporan perakaunan (PDF + Excel) — Carta Akaun, Buku Jurnal, Lejer.
 * Mengikut corak imbangan()/untungRugi() sedia ada (?format=pdf|xls).
 */
class EksportTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private AppUser $bendahari;

    protected function setUp(): void
    {
        parent::setUp();
        // Render DomPDF sebenar memakan memori; beri ruang kepala dalam proses
        // ujian berkongsi (had php.ini 128M tidak mencukupi selepas ratusan ujian).
        if ($this->kilobait(ini_get('memory_limit')) < 512 * 1024) {
            ini_set('memory_limit', '512M');
        }

        $this->aktifkanKonteksMasjid();

        $this->bendahari = AppUser::create([
            'masjid_id'     => config('spkm.masjid_id'),
            'login'         => 'uji_eksport_'.uniqid(),
            'nama_penuh'    => 'Ujian Eksport',
            'role'          => 'bendahari',
            'password_hash' => Hash::make('rahsia123'),
            'is_active'     => 1,
        ]);
    }

    /** Tukar nilai memory_limit (cth '128M', '512M', '-1') kepada kilobait. */
    private function kilobait(string|false $limit): int
    {
        if ($limit === false || $limit === '' || (int) $limit < 0) {
            return PHP_INT_MAX; // tiada had
        }
        $unit = strtolower(substr($limit, -1));
        $nilai = (int) $limit;

        return match ($unit) {
            'g' => $nilai * 1024 * 1024,
            'm' => $nilai * 1024,
            'k' => $nilai,
            default => (int) ($nilai / 1024),
        };
    }

    private function pdf(string $url): void
    {
        $resp = $this->actingAs($this->bendahari)->get($url);
        $resp->assertOk();
        $this->assertStringContainsString('application/pdf', strtolower($resp->headers->get('content-type')));
    }

    /**
     * Eksport Excel guna Excel::fake() — elak penjanaan spreadsheet sebenar
     * (zipstream/PhpSpreadsheet memakan memori) sambil mengesahkan controller
     * memulangkan muat turun JadualExport.
     */
    private function xls(string $url, string $namaFail): void
    {
        Excel::fake();

        $this->actingAs($this->bendahari)->get($url)->assertOk();

        Excel::assertDownloaded($namaFail, fn (JadualExport $export) => true);
    }

    public function test_eksport_carta_akaun(): void
    {
        $this->pdf(route('akaun.coa', ['format' => 'pdf']));
        $this->xls(route('akaun.coa', ['format' => 'xls']), 'carta-akaun.xlsx');
    }

    public function test_eksport_buku_jurnal(): void
    {
        // Julat sehari (data sejarah wujud) — kekal ringan untuk render PDF
        $params = ['date_from' => '2024-01-01', 'date_to' => '2024-01-01'];
        $this->pdf(route('akaun.jurnal', $params + ['format' => 'pdf']));
        $this->xls(route('akaun.jurnal', $params + ['format' => 'xls']),
            'buku-jurnal-2024-01-01-hingga-2024-01-01.xlsx');
    }

    public function test_eksport_lejer(): void
    {
        // 250-04010 SIMPANAN TETAP — akaun FD; julat sebulan ringan untuk PDF
        $params = [
            'coa_id'    => $this->coaId('250-04010'),
            'date_from' => '2024-01-01',
            'date_to'   => '2024-01-31',
        ];
        $this->pdf(route('akaun.lejer', $params + ['format' => 'pdf']));
        $this->xls(route('akaun.lejer', $params + ['format' => 'xls']),
            'lejer-250-04010-2024-01-01-hingga-2024-01-31.xlsx');
    }
}

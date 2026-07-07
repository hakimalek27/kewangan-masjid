<?php

namespace Tests\Feature\Laporan;

use App\Models\AppUser;
use App\Services\Laporan\DashboardService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Ujian HTTP UI Laporan (Fasa 3) — setiap route laporan mesti boleh dipapar
 * oleh bendahari dan mengandungi nilai kunci yang sudah disahkan tally
 * (TallySejarahTest) supaya paparan ikat terus pada angka jurnal.
 */
class LaporanHttpTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private AppUser $bendahari;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();

        $this->bendahari = AppUser::create([
            'masjid_id' => config('spkm.masjid_id'), 'login' => 'uji_laporan_'.uniqid(),
            'nama_penuh' => 'Ujian Laporan', 'role' => 'bendahari',
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
    }

    public function test_dashboard_papar_penerimaan_tahun_semasa(): void
    {
        $ringkasan = app(DashboardService::class)->ringkasan(now()->year);

        $this->actingAs($this->bendahari)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Penerimaan Tahunan')
            ->assertSee(number_format((float) $ringkasan['penerimaan'], 2))
            ->assertSee(number_format((float) $ringkasan['perbelanjaan'], 2));
    }

    public function test_imbangan_duga_jun_2026_seimbang(): void
    {
        $this->actingAs($this->bendahari)->get(route('akaun.imbangan', ['bln' => 6, 'year' => 2026]))
            ->assertOk()
            ->assertSee('Imbangan Duga')
            ->assertSee('SEIMBANG');
    }

    public function test_untung_rugi_dipapar(): void
    {
        $this->actingAs($this->bendahari)->get(route('akaun.untungrugi'))
            ->assertOk()
            ->assertSee('JUMLAH PENDAPATAN')
            ->assertSee('LEBIHAN/(KURANGAN)');
    }

    public function test_kunci_kira_kira_jun_2026_papar_total_aset(): void
    {
        $this->actingAs($this->bendahari)->get(route('akaun.kunci', ['bln' => 6, 'year' => 2026]))
            ->assertOk()
            ->assertSee('174,589.95')
            ->assertSee('SEIMBANG');
    }

    public function test_laporan_program_dipapar(): void
    {
        $this->actingAs($this->bendahari)->get(route('akaun.program'))
            ->assertOk()
            ->assertSee('IHYA RAMADAN');
    }

    public function test_penyata_bulanan_jan_2026_seimbang(): void
    {
        $this->actingAs($this->bendahari)->get(route('penyata.bulanan', ['bln' => 1, 'year' => 2026]))
            ->assertOk()
            ->assertSee('BUTIR TERIMAAN')
            ->assertSee('BUTIR PERBELANJAAN')
            ->assertSee('BAKI AWAL (B/B)')
            ->assertSee('SEIMBANG');
    }

    public function test_penyata_bank_dipapar(): void
    {
        $this->actingAs($this->bendahari)->get(route('penyata.bank', ['bln' => 1, 'year' => 2026]))
            ->assertOk()
            ->assertSee('BUTIR TERIMAAN');
    }

    public function test_penyata_tahunan_2025_tally(): void
    {
        // 919,449.89 = terima tunai 2025 (disahkan tally lawan V1); format 2-lajur tahunan
        $this->actingAs($this->bendahari)->get(route('penyata.tahunan', ['year' => 2025]))
            ->assertOk()
            ->assertSee('BAGI TAHUN BERAKHIR: 31 DISEMBER')
            ->assertSee('919,449.89');
    }

    public function test_statistik_kutipan_tahunan_2025(): void
    {
        $this->actingAs($this->bendahari)->get(route('statistik.kutipan', ['year' => 2025]))
            ->assertOk()
            ->assertSee('Statistik Kutipan Tahunan');
    }

    public function test_statistik_belanja_dan_bulanan_coa(): void
    {
        $this->actingAs($this->bendahari)->get(route('statistik.belanja', ['year' => 2025]))->assertOk();
        $this->actingAs($this->bendahari)->get(route('statistik.kutipan_coa', ['bln' => 1, 'year' => 2026]))->assertOk();
        $this->actingAs($this->bendahari)->get(route('statistik.belanja_coa', ['bln' => 1, 'year' => 2026]))->assertOk();
    }

    public function test_statistik_jumaat_dipapar(): void
    {
        $this->actingAs($this->bendahari)->get(route('statistik.jumaat', ['bln' => 1, 'year' => 2026]))
            ->assertOk()
            ->assertSee('Statistik Kutipan Jumaat');
    }

    public function test_penyata_pwr_dipapar(): void
    {
        $this->actingAs($this->bendahari)->get(route('pwr.penyata'))
            ->assertOk()
            ->assertSee('Penyata PWR');
    }

    public function test_baki_pwr_dipapar(): void
    {
        $this->actingAs($this->bendahari)->get(route('pwr.baki', ['year' => 2026]))
            ->assertOk()
            ->assertSee('Baki Di Tangan PWR');
    }

    public function test_buku_jurnal_dipapar(): void
    {
        $this->actingAs($this->bendahari)
            ->get(route('akaun.jurnal', ['date_from' => '2026-01-01', 'date_to' => '2026-01-31']))
            ->assertOk()
            ->assertSee('Buku Jurnal');
    }

    public function test_laporan_jurnal_dan_lejer_dipapar(): void
    {
        $this->actingAs($this->bendahari)->get(route('akaun.laporanjurnal'))->assertOk();
        $this->actingAs($this->bendahari)->get(route('akaun.lejer'))->assertOk()->assertSee('Lejer Am');
        $this->actingAs($this->bendahari)
            ->get(route('akaun.lejerakaun', [
                'coa_id' => $this->coaId('250-05010'), 'date_from' => '2026-01-01', 'date_to' => '2026-01-31',
            ]))
            ->assertOk()
            ->assertSee('BAKI AWAL');
    }

    public function test_carta_akaun_dipapar(): void
    {
        $this->actingAs($this->bendahari)->get(route('akaun.coa'))
            ->assertOk()
            ->assertSee('Carta Akaun');
    }
}

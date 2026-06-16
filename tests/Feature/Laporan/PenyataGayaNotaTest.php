<?php

namespace Tests\Feature\Laporan;

use App\Models\AppUser;
use App\Models\Kutipan;
use App\Models\Pembayaran;
use App\Services\Laporan\ReportService;
use App\Services\Transaksi\KutipanService;
use App\Support\Setting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Suis gaya penyata (V1 bersih / Semasa Bootstrap) + Nota Program + grid.
 * Paparan sahaja — tidak menyentuh GL/jumlah; penyata mesti kekal SEIMBANG kedua-dua gaya.
 */
class PenyataGayaNotaTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private AppUser $bendahari;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();

        $this->bendahari = AppUser::create([
            'masjid_id' => config('sppkms.masjid_id'), 'login' => 'uji_gaya_'.uniqid(),
            'nama_penuh' => 'Ujian Gaya', 'role' => 'bendahari',
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
    }

    public function test_lalai_gaya_v1_bersih(): void
    {
        // Tanpa tetapan → lalai 'v1' (bersih). Tajuk V1 hadir; tajuk Bootstrap "PENYATA KEWANGAN BULANAN" tiada.
        $this->actingAs($this->bendahari)->get(route('penyata.bulanan', ['bln' => 1, 'year' => 2026]))
            ->assertOk()
            ->assertSee('PENYATA RINGKASAN TERIMAAN &amp; PERBELANJAAN', false)
            ->assertSee('BUTIR TERIMAAN (RM)')
            ->assertDontSee('PENYATA KEWANGAN BULANAN')
            ->assertSee('SEIMBANG');
    }

    public function test_suis_ke_semasa_disimpan(): void
    {
        // Pilih gaya 'semasa' → halaman tunjuk gaya Bootstrap (tajuk PENYATA KEWANGAN BULANAN)
        $this->actingAs($this->bendahari)->get(route('penyata.bulanan', ['bln' => 1, 'year' => 2026, 'gaya' => 'semasa']))
            ->assertOk()
            ->assertSee('PENYATA KEWANGAN BULANAN');

        $this->assertSame('semasa', Setting::get('gaya_penyata'));

        // Lawatan berikut TANPA ?gaya → pilihan kekal 'semasa'
        $this->actingAs($this->bendahari)->get(route('penyata.bulanan', ['bln' => 1, 'year' => 2026]))
            ->assertOk()
            ->assertSee('PENYATA KEWANGAN BULANAN');

        // Tukar semula ke v1
        $this->actingAs($this->bendahari)->get(route('penyata.bulanan', ['gaya' => 'v1']))
            ->assertOk()
            ->assertDontSee('PENYATA KEWANGAN BULANAN');
    }

    public function test_nota_program_dipapar_bila_diminta(): void
    {
        // Tanpa nota → tiada nota
        $this->actingAs($this->bendahari)->get(route('penyata.tahunan', ['year' => 2025]))
            ->assertOk()
            ->assertDontSee('NOTA KEPADA PENYATA');

        // Dengan ?nota=1 → nota program + nota kaki bernombor + tanda superskrip dipapar
        $this->actingAs($this->bendahari)->get(route('penyata.tahunan', ['year' => 2025, 'nota' => 1]))
            ->assertOk()
            ->assertSee('NOTA KEPADA PENYATA — RINCIAN PROGRAM', false)
            ->assertSee('NOTA KAKI')
            ->assertSee('IHYA RAMADAN')
            ->assertSee('<sup style="font-weight:bold', false); // tanda nota kaki bold pada item penyata
    }

    public function test_grid_12_bulan_pilihan(): void
    {
        $this->actingAs($this->bendahari)->get(route('penyata.tahunan', ['year' => 2025]))
            ->assertOk()
            ->assertDontSee('Ringkasan Bulanan');

        $this->actingAs($this->bendahari)->get(route('penyata.tahunan', ['year' => 2025, 'grid' => 1]))
            ->assertOk()
            ->assertSee('Ringkasan Bulanan');
    }

    public function test_program_report_2025_tidak_kosong(): void
    {
        $nota = app(ReportService::class)->programReport('2025-01', '2025-12');
        $this->assertTrue($nota->isNotEmpty(), 'programReport 2025 mesti ada rekod');
        $this->assertTrue(
            $nota->contains(fn ($n) => $n->program === 'IHYA RAMADAN'),
            'Program IHYA RAMADAN sepatutnya wujud dalam nota 2025'
        );
    }

    public function test_pdf_bulanan_a3_dengan_nota_dijana(): void
    {
        $res = $this->actingAs($this->bendahari)
            ->get(route('penyata.bulanan', ['bln' => 1, 'year' => 2026, 'nota' => 1, 'format' => 'pdf']));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
    }

    public function test_pdf_tahunan_a3_dengan_nota_grid_dijana(): void
    {
        $res = $this->actingAs($this->bendahari)
            ->get(route('penyata.tahunan', ['year' => 2025, 'nota' => 1, 'grid' => 1, 'format' => 'pdf']));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
    }

    public function test_borang_kutipan_ada_medan_program(): void
    {
        $this->actingAs($this->bendahari)->get(route('kutipan.baru'))
            ->assertOk()
            ->assertSee('name="program"', false)
            ->assertSee('senarai-program', false); // datalist cadangan program
    }

    public function test_borang_bayaran_ada_medan_program(): void
    {
        $this->actingAs($this->bendahari)->get(route('belanja.baru'))
            ->assertOk()
            ->assertSee('name="program"', false)
            ->assertSee('senarai-program', false);
    }

    public function test_kutipan_web_simpan_program_dan_muncul_nota(): void
    {
        // Simulasi bendahari masuk kutipan via WEB dengan tag program akan datang
        $this->actingAs($this->bendahari)->post(route('kutipan.simpan'), [
            'coa_id'       => $this->coaId('400-03010'),
            'kaedah'       => 'TUNAI',
            'tarikh'       => '2026-06-16',
            'jumlah'       => '88.00',
            'auto_resit'   => '1',
            'nama_pemberi' => 'Ujian Nota',
            'program'      => 'UJIAN NOTA PROGRAM',
            'semakan'      => '1',
        ])->assertRedirect();

        // Program tersimpan (bukan NULL) — sebab itu nota akan auto-terhasil
        $rec = Kutipan::where('program', 'UJIAN NOTA PROGRAM')->first();
        $this->assertNotNull($rec, 'Kutipan web mesti simpan tag program');
        $this->assertSame('2026-06', $rec->period_ym);

        // Dan muncul dalam programReport tempoh berkenaan (sumber nota kaki)
        $nota = app(ReportService::class)->programReport('2026-06', '2026-06');
        $this->assertTrue($nota->contains(fn ($n) => $n->program === 'UJIAN NOTA PROGRAM'),
            'Program web baru mesti muncul dalam nota');
    }

    public function test_belanja_web_simpan_program(): void
    {
        Setting::set('approval_threshold', '0'); // pastikan tiada maker-checker mengganggu
        $this->actingAs($this->bendahari)->post(route('belanja.simpan'), [
            'tar_mohon'  => '2026-06-16', 'tar_lulus' => '2026-06-16', 'auto_baucer' => '1',
            'pemohon'    => 'Ujian Bayar', 'coa_id' => $this->coaId('600-11000'), 'jumlah' => '12.00',
            'cara_bayar' => 'PWR', 'pwr_coa_id' => $this->coaId('250-06000'),
            'program'    => 'UJIAN BAYAR PROGRAM', 'semakan' => '1',
        ])->assertRedirect();

        $this->assertNotNull(Pembayaran::where('program', 'UJIAN BAYAR PROGRAM')->first(),
            'Bayaran web mesti simpan tag program');
    }

    public function test_program_kosong_dinormal_ke_null(): void
    {
        // Ruang kosong sahaja → null (elak '' bocor ke nota)
        $this->actingAs($this->bendahari)->post(route('kutipan.simpan'), [
            'coa_id' => $this->coaId('400-03010'), 'kaedah' => 'TUNAI', 'tarikh' => '2026-06-20',
            'jumlah' => '15.00', 'auto_resit' => '1', 'program' => '   ', 'semakan' => '1',
        ])->assertRedirect();

        $rec = Kutipan::where('tarikh', '2026-06-20')->where('jumlah', 15.00)->first();
        $this->assertNotNull($rec);
        $this->assertNull($rec->program, 'Program ruang-kosong mesti jadi NULL');
    }

    public function test_void_dikecualikan_dari_nota(): void
    {
        $svc = app(KutipanService::class);
        $k = $svc->create([
            'coa_id' => $this->coaId('400-03010'), 'kaedah' => 'TUNAI', 'tarikh' => '2026-07-01',
            'jumlah' => 30, 'auto_resit' => true, 'program' => 'UJIAN VOID NOTA', 'semakan' => true,
        ]);
        $svc->void($k, 'ujian');

        $nota = app(ReportService::class)->programReport('2026-07', '2026-07');
        $this->assertFalse($nota->contains(fn ($n) => $n->program === 'UJIAN VOID NOTA'),
            'Rekod VOID mesti dikecualikan dari nota');
    }

    public function test_nota_kaki_rekonsil_dgn_baki_tidak_bertag(): void
    {
        // COA sama: satu bertag (100) + satu tanpa tag (40) → baris penyata 140;
        // nota kaki mesti tunjuk program 100 + "(Tiada tag program)" 40 = 140 (rekonsil).
        $svc = app(KutipanService::class);
        $svc->create(['coa_id' => $this->coaId('400-03010'), 'kaedah' => 'TUNAI', 'tarikh' => '2026-08-05',
            'jumlah' => 100, 'auto_resit' => true, 'program' => 'UJIAN SEBAHAGIAN', 'semakan' => true]);
        $svc->create(['coa_id' => $this->coaId('400-03010'), 'kaedah' => 'TUNAI', 'tarikh' => '2026-08-06',
            'jumlah' => 40, 'auto_resit' => true, 'semakan' => true]); // tiada program

        $this->actingAs($this->bendahari)->get(route('penyata.bulanan', ['bln' => 8, 'year' => 2026, 'nota' => 1]))
            ->assertOk()
            ->assertSee('UJIAN SEBAHAGIAN')
            ->assertSee('(Tiada tag program)');
    }

    public function test_gaya_semasa_marker_dipapar(): void
    {
        $this->actingAs($this->bendahari)->get(route('penyata.tahunan', ['year' => 2025, 'gaya' => 'semasa', 'nota' => 1]))
            ->assertOk()
            ->assertSee('<sup class="fw-bold"', false); // tanda nota dalam jadual Bootstrap
    }

    public function test_xls_eksport_penyata_ok(): void
    {
        $this->actingAs($this->bendahari)->get(route('penyata.bulanan', ['bln' => 1, 'year' => 2026, 'format' => 'xls']))
            ->assertOk();
        $this->actingAs($this->bendahari)->get(route('penyata.tahunan', ['year' => 2025, 'format' => 'xls']))
            ->assertOk();
    }

    public function test_nota_tempoh_kosong(): void
    {
        // Tempoh tiada data program → tiada NOTA KAKI; jadual Rincian Program papar "Tiada program bertag"
        $this->actingAs($this->bendahari)->get(route('penyata.bulanan', ['bln' => 1, 'year' => 2027, 'nota' => 1]))
            ->assertOk()
            ->assertDontSee('NOTA KAKI')
            ->assertSee('Tiada program bertag');
    }

    public function test_program_by_coa_dwi_sisi_dan_bank_param(): void
    {
        // 300-04050 wujud di KEDUA-DUA terimaan & belanja → nombor nota mesti berasingan (SISI+kod)
        $pbc = app(ReportService::class)->programByCoa('2025-01', '2025-12');
        $this->assertTrue($pbc['terimaan']->has('300-04050'), 'terimaan patut ada 300-04050');
        $this->assertTrue($pbc['belanja']->has('300-04050'), 'belanja patut ada 300-04050');
        // Param bank tidak menyebabkan ralat
        $this->assertIsArray(app(ReportService::class)->programByCoa('2025-01', '2025-12', null, 1));
    }

    public function test_penyata_bank_dgn_nota_ok(): void
    {
        $bankId = \App\Models\BankAccount::withoutMasjidScope()
            ->where('masjid_id', config('sppkms.masjid_id'))->value('id');
        $this->actingAs($this->bendahari)
            ->get(route('penyata.bank', ['bank_account_id' => $bankId, 'bln' => 1, 'year' => 2026, 'nota' => 1]))
            ->assertOk()
            ->assertSee('SEIMBANG');
    }

    public function test_seimbang_kekal_kedua_gaya(): void
    {
        foreach (['v1', 'semasa'] as $gaya) {
            $this->actingAs($this->bendahari)->get(route('penyata.bulanan', ['bln' => 1, 'year' => 2026, 'gaya' => $gaya]))
                ->assertOk()->assertSee('SEIMBANG');
            $this->actingAs($this->bendahari)->get(route('penyata.tahunan', ['year' => 2025, 'gaya' => $gaya]))
                ->assertOk()->assertSee('SEIMBANG');
        }
    }
}

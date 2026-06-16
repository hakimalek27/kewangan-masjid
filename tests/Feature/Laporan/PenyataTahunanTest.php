<?php

namespace Tests\Feature\Laporan;

use App\Models\AppUser;
use App\Services\Laporan\StatementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Penyata Tahunan format 2-lajur (yearlyStatement) — invarian:
 * seimbang, konsisten dengan ringkasanTunai, baki_akhir(Y)=baki_awal(Y+1).
 */
class PenyataTahunanTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
    }

    public function test_yearly_statement_seimbang(): void
    {
        $s = app(StatementService::class);
        foreach ([2024, 2025, 2026] as $y) {
            $ps = $s->yearlyStatement($y);
            $this->assertSame($ps['jumlah_kiri'], $ps['jumlah_kanan'], "Penyata tahunan {$y} mesti SEIMBANG");
        }
    }

    public function test_yearly_terimaan_padan_ringkasan_tunai(): void
    {
        $s = app(StatementService::class);
        $ps = $s->yearlyStatement(2025);
        $rk = $s->ringkasanTunai('2025-01', '2025-12');
        $this->assertSame($rk['terima'], $ps['jumlah_terimaan'], 'Jumlah terimaan tahunan mesti = ringkasanTunai.terima');
    }

    public function test_baki_akhir_chain_ke_tahun_berikut(): void
    {
        $s = app(StatementService::class);
        $this->assertSame(
            $s->yearlyStatement(2024)['jumlah_baki_akhir'],
            $s->yearlyStatement(2025)['jumlah_baki_awal'],
            'Baki akhir 2024 mesti = baki awal 2025'
        );
    }

    public function test_halaman_penyata_tahunan_format_baharu(): void
    {
        $viewer = AppUser::create([
            'masjid_id' => config('sppkms.masjid_id'), 'login' => 'uji_pt_'.uniqid(),
            'nama_penuh' => 'Ujian PT', 'role' => 'viewer',
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);

        $this->actingAs($viewer)->get(route('penyata.tahunan', ['year' => 2025]))
            ->assertOk()
            ->assertSee('PENYATA TERIMAAN &amp; PERBELANJAAN (TAHUNAN)', false)
            ->assertSee('BAGI TAHUN BERAKHIR: 31 DISEMBER')
            ->assertSee('SEIMBANG');
    }
}

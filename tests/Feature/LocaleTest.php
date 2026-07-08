<?php

namespace Tests\Feature;

use App\Models\AppUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Dwibahasa BM/EN — UI bertukar mengikut sesi 'lang'. BM = sumber (kunci = teks
 * BM verbatim), EN melalui lang/en.json. Output penyata PDF kekal BM (audit rasmi).
 */
class LocaleTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private AppUser $pengguna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
        $this->pengguna = AppUser::create([
            'masjid_id' => config('spkm.masjid_id'), 'login' => 'uji_lang_'.uniqid(),
            'nama_penuh' => 'Pengguna', 'role' => 'admin',
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
    }

    public function test_bahasa_inggeris_papar_terjemahan(): void
    {
        // 'selected_masjid_id' → admin dalam mod dalam-tenant (halaman kewangan dirender).
        $this->withSession(['lang' => 'en', 'selected_masjid_id' => (int) config('spkm.masjid_id')]);

        // Sidebar + dropdown bahasa wujud
        $this->actingAs($this->pengguna)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Settings')          // menu 'Tetapan'
            ->assertSee('Contact Us');       // 'Hubungi Kami'

        // Borang kutipan label EN
        $this->actingAs($this->pengguna)->get(route('kutipan.baru'))
            ->assertOk()
            ->assertSee('Collection Date')      // 'Tarikh Kutipan' diterjemah
            ->assertSee('Donor Name');          // 'Nama Pemberi' diterjemah

        // Wizard tajuk EN
        $this->actingAs($this->pengguna)->get(route('tetapan.wizard'))
            ->assertOk()
            ->assertSee('Setup Progress');   // 'Kemajuan Setup'
    }

    public function test_bahasa_melayu_papar_teks_asal(): void
    {
        $this->withSession(['lang' => 'ms', 'selected_masjid_id' => (int) config('spkm.masjid_id')]);

        $this->actingAs($this->pengguna)->get(route('tetapan.wizard'))
            ->assertOk()
            ->assertSee('Kemajuan Setup')
            ->assertDontSee('Setup Progress');
    }

    public function test_suis_bahasa_simpan_dalam_sesi(): void
    {
        $this->actingAs($this->pengguna)->get(route('bahasa', 'en'))->assertRedirect();
        $this->assertSame('en', session('lang'));

        $this->actingAs($this->pengguna)->get(route('bahasa', 'ms'))->assertRedirect();
        $this->assertSame('ms', session('lang'));
    }
}

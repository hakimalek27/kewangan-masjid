<?php

namespace Tests\Feature\Lanjutan;

use App\Models\AppUser;
use App\Models\ErrorLog;
use App\Services\Lanjutan\BakiRendahService;
use App\Support\Setting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * T1a notifikasi baki rendah + T1b amaran defisit dana pada borang.
 */
class BakiRendahDanaTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private AppUser $bendahari;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
        Http::fake(); // sekat Telegram sebenar

        $this->bendahari = AppUser::create([
            'masjid_id' => config('sppkms.masjid_id'), 'login' => 'uji_br_'.uniqid(),
            'nama_penuh' => 'Bendahari', 'role' => 'bendahari',
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
    }

    // ---------- T1a: Baki rendah ----------

    public function test_ambang_sifar_tiada_notifikasi(): void
    {
        Setting::set('baki_rendah_ambang', '0');

        $this->assertSame([], app(BakiRendahService::class)->senaraiRendah());
        $this->assertSame(0, app(BakiRendahService::class)->semakSemua());
    }

    public function test_ambang_tinggi_kesan_baki_rendah_dan_log_sekali_sehari(): void
    {
        // Bank sebenar ~RM173k — ambang RM999 juta menjadikan semua bank "rendah"
        Setting::set('baki_rendah_ambang', '999999999');

        $svc = app(BakiRendahService::class);
        $rendah = $svc->senaraiRendah();
        $this->assertNotEmpty($rendah);
        $this->assertSame('250-05010', $rendah[0]->kod);

        // Semakan berjadual → ErrorLog INFO + hanya SEKALI sehari
        $this->assertGreaterThanOrEqual(1, $svc->semakSemua());
        $this->assertSame(0, $svc->semakSemua(), 'Kali kedua hari sama tidak patut log lagi');

        $this->assertSame(1, ErrorLog::withoutMasjidScope()
            ->where('masjid_id', config('sppkms.masjid_id'))
            ->where('message', 'like', 'Baki bank rendah%')
            ->whereDate('created_at', now()->toDateString())
            ->count());
    }

    public function test_loceng_papar_baki_rendah(): void
    {
        Setting::set('baki_rendah_ambang', '999999999');

        $this->actingAs($this->bendahari)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Baki rendah:');
    }

    public function test_simpan_ambang_melalui_kawalan(): void
    {
        // Kawalan dalaman = tetapan aras-masjid → dikemaskini oleh BENDAHARI (bukan admin sistem).
        $this->actingAs($this->bendahari)->post(route('kawalan.simpan'), [
            'approval_threshold' => '0',
            'baki_rendah_ambang' => '5000',
        ])->assertRedirect(route('kawalan.index'));

        $this->assertSame('5000.00', Setting::get('baki_rendah_ambang'));
    }

    // ---------- T1b: Amaran defisit dana pada borang ----------

    public function test_dana_semak_defisit_rahmah_madani(): void
    {
        Setting::set('fund_deficit_alert', 'on');

        // 300-04050 Rahmah Madani — baki sejarah −23,850 (defisit sedia ada)
        $resp = $this->actingAs($this->bendahari)
            ->getJson(route('dana.semak', ['coa_id' => $this->coaId('300-04050'), 'jumlah' => '1.00']))
            ->assertOk()
            ->json();

        $this->assertTrue($resp['dana']);
        $this->assertTrue($resp['defisit_selepas']);
        $this->assertSame('-23850.00', $resp['baki']);
        $this->assertSame('-23851.00', $resp['baki_selepas']);
    }

    public function test_dana_semak_coa_bukan_dana(): void
    {
        $resp = $this->actingAs($this->bendahari)
            ->getJson(route('dana.semak', ['coa_id' => $this->coaId('600-06000'), 'jumlah' => '100.00']))
            ->assertOk()
            ->json();

        $this->assertFalse($resp['dana']);
    }

    public function test_dana_semak_dimatikan_melalui_setting(): void
    {
        Setting::set('fund_deficit_alert', 'off');

        $resp = $this->actingAs($this->bendahari)
            ->getJson(route('dana.semak', ['coa_id' => $this->coaId('300-04050'), 'jumlah' => '1.00']))
            ->assertOk()
            ->json();

        $this->assertFalse($resp['dana']);
    }

    public function test_borang_belanja_mengandungi_fund_warning(): void
    {
        Setting::set('fund_deficit_alert', 'on');

        $this->actingAs($this->bendahari)
            ->get(route('belanja.baru'))
            ->assertOk()
            ->assertSee('dana/semak', false); // komponen x-fund-warning dipasang
    }
}

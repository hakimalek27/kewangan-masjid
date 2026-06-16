<?php

namespace Tests\Feature\MultiMasjid;

use App\Models\AppUser;
use App\Models\Masjid;
use App\Services\Tetapan\CoaTemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Phase B — onboarding masjid baharu: admin cipta rekod masjid + login bendahari pertama
 * (satu transaksi); bukan-admin disekat; bendahari baharu terpencil ke masjidnya.
 */
class MasjidOnboardingTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private AppUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
        $this->admin = $this->buatUser('admin');
    }

    private function buatUser(string $role): AppUser
    {
        return AppUser::create([
            'masjid_id' => config('sppkms.masjid_id'), 'login' => 'uji_'.$role.'_'.uniqid(),
            'nama_penuh' => 'Uji '.$role, 'role' => $role,
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
    }

    public function test_admin_cipta_masjid_dan_bendahari_pertama(): void
    {
        $login = 'bdh_'.uniqid();
        $this->actingAs($this->admin)->post(route('tetapan.masjid.baru.simpan'), [
            'nama' => 'Masjid Baharu Ujian', 'kategori' => 'MASJID KARIAH', 'negeri' => 'Selangor',
            'login' => $login, 'nama_penuh' => 'Bendahari Baharu', 'kata_laluan' => 'rahsia123',
        ])->assertRedirect(route('tetapan.pengguna'));

        $masjid = Masjid::where('nama', 'Masjid Baharu Ujian')->first();
        $this->assertNotNull($masjid, 'Masjid baharu mesti dicipta');

        $bdh = AppUser::where('login', $login)->first();
        $this->assertNotNull($bdh, 'Login bendahari mesti dicipta');
        $this->assertSame((int) $masjid->id, (int) $bdh->masjid_id);
        $this->assertSame('bendahari', $bdh->role->value);
        // Bendahari baharu terpencil ke masjid baharu sahaja
        $this->assertSame([(int) $masjid->id], $bdh->accessibleMasjidIds());

        // COA standard disemai → masjid baharu terus boleh berfungsi (bilangan sama dgn templat)
        $bilTemplat = (int) DB::table('coa')->where('masjid_id', config('sppkms.masjid_id'))->count();
        $bilBaharu = (int) DB::table('coa')->where('masjid_id', $masjid->id)->count();
        $this->assertGreaterThan(0, $bilBaharu, 'Masjid baharu mesti dapat COA standard');
        $this->assertSame($bilTemplat, $bilBaharu, 'COA masjid baharu mesti sama bilangan dgn templat');
    }

    public function test_semai_coa_idempoten(): void
    {
        $svc = app(CoaTemplateService::class);
        $masjidId = (int) Masjid::create(['nama' => 'Masjid COA '.uniqid()])->id;

        $bil1 = $svc->sediaUntukMasjid($masjidId);
        $this->assertGreaterThan(0, $bil1, 'Semaian pertama mesti cipta akaun');

        $bil2 = $svc->sediaUntukMasjid($masjidId); // kedua kali → tiada gandaan
        $this->assertSame(0, $bil2, 'Semaian kedua mesti idempoten (0)');
        $this->assertSame($bil1, (int) DB::table('coa')->where('masjid_id', $masjidId)->count());
    }

    public function test_bukan_admin_tidak_boleh_onboard(): void
    {
        $bendahari = $this->buatUser('bendahari');
        $this->actingAs($bendahari)->get(route('tetapan.masjid.baru'))->assertForbidden();
        $this->actingAs($bendahari)->post(route('tetapan.masjid.baru.simpan'), [
            'nama' => 'Masjid Haram', 'login' => 'x_'.uniqid(), 'nama_penuh' => 'Y', 'kata_laluan' => 'rahsia123',
        ])->assertForbidden();
        $this->assertNull(Masjid::where('nama', 'Masjid Haram')->first());
    }

    public function test_onboard_gagal_dan_gulung_balik_jika_templat_coa_kosong(): void
    {
        // Templat COA tunjuk ke masjid TANPA COA → semaian 0 → SELURUH transaksi gulung balik.
        $kosong = (int) Masjid::create(['nama' => 'Templat Kosong '.uniqid()])->id;
        config(['sppkms.masjid_id' => $kosong]);

        $login = 'bdh_'.uniqid();
        $this->actingAs($this->admin)->post(route('tetapan.masjid.baru.simpan'), [
            'nama' => 'Masjid Tanpa COA', 'kategori' => 'MASJID KARIAH', 'negeri' => 'Selangor',
            'login' => $login, 'nama_penuh' => 'Bendahari X', 'kata_laluan' => 'rahsia123',
        ])->assertSessionHasErrors('nama');

        // Tiada masjid yatim & tiada login yatim dicipta (atomik)
        $this->assertNull(Masjid::where('nama', 'Masjid Tanpa COA')->first());
        $this->assertNull(AppUser::where('login', $login)->first());
    }

    public function test_login_duplikat_ditolak_tanpa_cipta_masjid(): void
    {
        $this->actingAs($this->admin)->post(route('tetapan.masjid.baru.simpan'), [
            'nama' => 'Masjid Dup', 'login' => $this->admin->login, // login sedia ada
            'nama_penuh' => 'Z', 'kata_laluan' => 'rahsia123',
        ])->assertSessionHasErrors('login');

        // Validasi gagal sebelum transaksi → tiada masjid yatim dicipta
        $this->assertNull(Masjid::where('nama', 'Masjid Dup')->first());
    }
}

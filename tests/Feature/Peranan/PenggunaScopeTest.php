<?php

namespace Tests\Feature\Peranan;

use App\Models\AppUser;
use App\Models\Masjid;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Pengurusan pengguna ber-skop: BENDAHARI hanya urus pengguna masjid SENDIRI dan
 * TIDAK boleh melantik/mengubah akaun 'admin'; ADMIN = semua masjid, mana-mana peranan.
 */
class PenggunaScopeTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private int $home;

    private int $masjidB;

    private AppUser $bendahari;

    private AppUser $admin;

    private AppUser $adminHome;

    private AppUser $userB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
        $this->home = (int) config('spkm.masjid_id');
        $this->masjidB = (int) Masjid::create(['nama' => 'Masjid B '.uniqid()])->id;

        $this->bendahari = $this->buat('bendahari', $this->home);
        $this->admin = $this->buat('admin', $this->home);
        $this->adminHome = $this->buat('admin', $this->home);   // admin lain dlm masjid sendiri
        $this->userB = $this->buat('bendahari', $this->masjidB); // pengguna masjid LAIN
    }

    private function buat(string $role, int $masjidId): AppUser
    {
        return AppUser::create([
            'masjid_id' => $masjidId, 'login' => 'uji_'.$role.'_'.uniqid(),
            'nama_penuh' => 'Uji '.$role, 'role' => $role,
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
    }

    private function borang(array $ubah = []): array
    {
        return array_merge([
            'login' => 'baru_'.uniqid(), 'nama_penuh' => 'Pengguna Baru',
            'role' => 'juruaudit', 'masjid_id' => $this->home, 'kata_laluan' => 'rahsia123', 'is_active' => '1',
        ], $ubah);
    }

    public function test_bendahari_lihat_pengguna_masjid_sendiri_sahaja(): void
    {
        $resp = $this->actingAs($this->bendahari)->get(route('tetapan.pengguna'));
        $resp->assertOk();
        $resp->assertSee($this->bendahari->login);
        $resp->assertDontSee($this->userB->login); // pengguna masjid B tidak kelihatan
    }

    public function test_bendahari_cipta_pengguna_dipaksa_masjid_sendiri(): void
    {
        $login = 'su_'.uniqid();
        // Cuba hantar masjid_id = B → controller PAKSA ke masjid semasa (49)
        $this->actingAs($this->bendahari)->post(route('tetapan.pengguna.simpan'),
            $this->borang(['login' => $login, 'role' => 'juruaudit', 'masjid_id' => $this->masjidB]))
            ->assertRedirect(route('tetapan.pengguna'))->assertSessionHasNoErrors();

        $baru = AppUser::where('login', $login)->first();
        $this->assertNotNull($baru);
        $this->assertSame($this->home, (int) $baru->masjid_id); // dipaksa ke masjid sendiri
        $this->assertSame('juruaudit', $baru->role->value);
    }

    public function test_bendahari_tak_boleh_lantik_admin(): void
    {
        $login = 'x_'.uniqid();
        $this->actingAs($this->bendahari)->post(route('tetapan.pengguna.simpan'),
            $this->borang(['login' => $login, 'role' => 'admin']))
            ->assertSessionHasErrors('role');
        $this->assertNull(AppUser::where('login', $login)->first());
    }

    public function test_bendahari_tak_boleh_urus_pengguna_masjid_lain(): void
    {
        $this->actingAs($this->bendahari)->get(route('tetapan.pengguna.edit', $this->userB->id))->assertForbidden();
        $this->actingAs($this->bendahari)->post(route('tetapan.pengguna.kemaskini', $this->userB->id),
            $this->borang(['login' => $this->userB->login, 'role' => 'bendahari']))->assertForbidden();
    }

    public function test_bendahari_tak_boleh_urus_akaun_admin(): void
    {
        $this->actingAs($this->bendahari)->get(route('tetapan.pengguna.edit', $this->adminHome->id))->assertForbidden();
    }

    public function test_admin_boleh_cipta_admin_mana_mana_masjid(): void
    {
        $login = 'adm_'.uniqid();
        $this->actingAs($this->admin)->post(route('tetapan.pengguna.simpan'),
            $this->borang(['login' => $login, 'role' => 'admin', 'masjid_id' => $this->masjidB]))
            ->assertRedirect(route('tetapan.pengguna'))->assertSessionHasNoErrors();

        $baru = AppUser::where('login', $login)->first();
        $this->assertNotNull($baru);
        $this->assertSame($this->masjidB, (int) $baru->masjid_id);
        $this->assertSame('admin', $baru->role->value);
    }
}

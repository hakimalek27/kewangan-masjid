<?php

namespace Tests\Feature\Peranan;

use App\Models\AppUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Pendaratan log masuk ikut peranan + akses Konsol Sistem.
 * admin → Konsol Sistem; pemerhati → penyata; lain → dashboard kewangan.
 */
class RoleLandingTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
    }

    private function buat(string $role): AppUser
    {
        return AppUser::create([
            'masjid_id' => config('spkm.masjid_id'), 'login' => 'land_'.$role.'_'.uniqid(),
            'nama_penuh' => 'Uji '.$role, 'role' => $role,
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
    }

    public function test_pendaratan_log_masuk_ikut_peranan(): void
    {
        $jangka = [
            'admin'      => route('sistem.console'),
            'pentadbir'  => route('dashboard'),
            'bendahari'  => route('dashboard'),
            'pengerusi'  => route('dashboard'),
            'setiausaha' => route('dashboard'),
            'juruaudit'  => route('dashboard'),
            'viewer'     => route('penyata.bulanan'),
        ];

        foreach ($jangka as $role => $tujuan) {
            $u = $this->buat($role);
            $this->post(route('login.attempt'), ['login' => $u->login, 'password' => 'rahsia123'])
                ->assertRedirect($tujuan);
            $this->post(route('logout')); // kembali ke tetamu untuk lelaran seterusnya
        }
    }

    public function test_konsol_sistem_admin_sahaja(): void
    {
        $this->actingAs($this->buat('admin'))->get(route('sistem.console'))->assertOk();
        // Bendahari (bukan admin) → 403
        $this->actingAs($this->buat('bendahari'))->get(route('sistem.console'))->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\AppUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use DatabaseTransactions;

    private function buatPengguna(string $role = 'bendahari'): AppUser
    {
        return AppUser::create([
            'masjid_id'     => config('spkm.masjid_id'),
            'login'         => 'ujian_'.uniqid(),
            'nama_penuh'    => 'Pengguna Ujian',
            'role'          => $role,
            'password_hash' => Hash::make('rahsia123'),
            'is_active'     => 1,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login:127.0.0.1');
    }

    public function test_halaman_login_dipapar(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Log Masuk')
            ->assertSee('hp_field', false);
    }

    public function test_login_berjaya_redirect_ke_dashboard(): void
    {
        $user = $this->buatPengguna();

        $this->post('/login', [
            'login'    => $user->login,
            'password' => 'rahsia123',
            'hp_field' => '',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_kata_laluan_salah_gagal(): void
    {
        $user = $this->buatPengguna();

        $this->from('/')->post('/login', [
            'login'    => $user->login,
            'password' => 'salah',
            'hp_field' => '',
        ])->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_honeypot_terisi_gagal_walaupun_kredensial_betul(): void
    {
        $user = $this->buatPengguna();

        $this->from('/')->post('/login', [
            'login'    => $user->login,
            'password' => 'rahsia123',
            'hp_field' => 'bot-mengisi-ini',
        ])->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseHas('security_event', ['jenis' => 'LOGIN_FAIL']);
    }

    public function test_pengguna_tidak_aktif_gagal_login(): void
    {
        $user = $this->buatPengguna();
        $user->update(['is_active' => 0]);

        $this->post('/login', [
            'login'    => $user->login,
            'password' => 'rahsia123',
            'hp_field' => '',
        ]);

        $this->assertGuest();
    }

    public function test_percubaan_login_direkod(): void
    {
        $user = $this->buatPengguna();

        $this->post('/login', ['login' => $user->login, 'password' => 'salah', 'hp_field' => '']);

        $this->assertDatabaseHas('login_attempt', ['login' => $user->login, 'success' => 0]);
    }

    public function test_dashboard_perlu_log_masuk(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_dashboard_dipapar_selepas_login(): void
    {
        $user = $this->buatPengguna();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Penerimaan Tahunan');
    }

    public function test_logout_berfungsi(): void
    {
        $user = $this->buatPengguna();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}

<?php

namespace Tests\Feature\Peranan;

use App\Models\AppUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Fasa 1c go-live: akaun dengan must_change_password dipaksa ke halaman tukar
 * kata laluan sebelum akses lain; flag hilang selepas tukar.
 */
class PaksaTukarKataLaluanTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
    }

    private function buat(bool $mesti): AppUser
    {
        return AppUser::create([
            'masjid_id' => config('sppkms.masjid_id'), 'login' => 'pk_'.uniqid(),
            'nama_penuh' => 'Uji', 'role' => 'bendahari',
            'password_hash' => Hash::make('lalai12345'), 'is_active' => 1,
            'must_change_password' => $mesti,
        ]);
    }

    public function test_dipaksa_ke_tukar_kata_laluan_bila_flag_hidup(): void
    {
        $user = $this->buat(true);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('tetapan.katalaluan'));

        // Halaman tukar kata laluan sendiri BOLEH diakses (elak gelung).
        $this->actingAs($user)->get(route('tetapan.katalaluan'))->assertOk();
    }

    public function test_flag_hilang_selepas_tukar_dan_akses_pulih(): void
    {
        $user = $this->buat(true);

        $this->actingAs($user)->post(route('tetapan.katalaluan.kemaskini'), [
            'kata_semasa' => 'lalai12345',
            'kata_baharu' => 'baharu98765',
            'kata_baharu_confirmation' => 'baharu98765',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->must_change_password);
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
    }

    public function test_pengguna_biasa_tidak_terjejas(): void
    {
        $user = $this->buat(false);
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }
}

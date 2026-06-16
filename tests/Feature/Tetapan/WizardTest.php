<?php

namespace Tests\Feature\Tetapan;

use App\Models\AppUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

class WizardTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
    }

    private function pengguna(string $role): AppUser
    {
        return AppUser::create([
            'masjid_id' => config('sppkms.masjid_id'), 'login' => 'uji_wz_'.$role.'_'.uniqid(),
            'nama_penuh' => 'Ujian', 'role' => $role,
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
    }

    public function test_wizard_papar_status_langkah(): void
    {
        // DB sejarah sudah ada bank + mapping + siri → sekurang-kurangnya separa siap
        $this->actingAs($this->pengguna('admin'))
            ->get(route('tetapan.wizard'))
            ->assertOk()
            ->assertSee('Kemajuan Setup')
            ->assertSee('Daftar Akaun Bank')
            ->assertSee('Set Baki Awal')
            ->assertSee('langkah selesai');
    }
}

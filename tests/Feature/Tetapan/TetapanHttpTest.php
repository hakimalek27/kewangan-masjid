<?php

namespace Tests\Feature\Tetapan;

use App\Models\AppUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Ujian HTTP Modul Tetapan (Fasa 4): paparan halaman, kawalan peranan,
 * tukar kata laluan, mapping kod & carian padanan fuzzy.
 */
class TetapanHttpTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private AppUser $admin;
    private AppUser $bendahari;
    private AppUser $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();

        $buat = fn (string $role) => AppUser::create([
            'masjid_id' => config('sppkms.masjid_id'), 'login' => 'uji_'.$role.'_'.uniqid(),
            'nama_penuh' => 'Ujian '.$role, 'role' => $role,
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
        $this->admin = $buat('admin');
        $this->bendahari = $buat('bendahari');
        $this->viewer = $buat('viewer');
    }

    public function test_semua_halaman_tetapan_dipapar_sebagai_bendahari(): void
    {
        $halaman = [
            'bank.index', 'bank.opening', 'bank.baki', 'tetapan.resit',
            'tetapan.mapping', 'tetapan.semak', 'tetapan.masjid',
            'tetapan.katalaluan', 'penyata.setting',
        ];

        foreach ($halaman as $nama) {
            $this->actingAs($this->bendahari)->get(route($nama))->assertOk();
        }
    }

    public function test_halaman_pengguna_dipapar_sebagai_admin(): void
    {
        $this->actingAs($this->admin)->get(route('tetapan.pengguna'))
            ->assertOk()
            ->assertSee('Pengurusan Pengguna');
    }

    public function test_tukar_kata_laluan_berjaya(): void
    {
        $this->actingAs($this->bendahari)->post(route('tetapan.katalaluan.kemaskini'), [
            'kata_semasa'              => 'rahsia123',
            'kata_baharu'              => 'baharu456',
            'kata_baharu_confirmation' => 'baharu456',
        ])->assertRedirect(route('tetapan.katalaluan'));

        $this->assertTrue(Hash::check('baharu456', $this->bendahari->fresh()->password_hash));
    }

    public function test_tambah_mapping_berjaya(): void
    {
        $this->actingAs($this->bendahari)->post(route('tetapan.mapping.simpan'), [
            'local_label' => 'UJIAN LABEL TETAPAN',
            'jenis_guna'  => 'kedua',
            'coa_id'      => $this->coaId('600-10050'),
        ])->assertRedirect(route('tetapan.mapping'));

        $this->assertDatabaseHas('coa_local_mapping', [
            'masjid_id'   => config('sppkms.masjid_id'),
            'local_label' => 'UJIAN LABEL TETAPAN',
            'jenis_guna'  => 'kedua',
            'coa_id'      => $this->coaId('600-10050'),
        ]);
    }

    public function test_viewer_dialih_ke_penyata_dan_tak_boleh_menulis(): void
    {
        // Pemerhati (Phase A) = penyata sahaja → halaman bukan-penyata dialih ke penyata bulanan
        $this->actingAs($this->viewer)->get(route('bank.index'))->assertRedirect(route('penyata.bulanan'));

        $this->actingAs($this->viewer)->post(route('bank.simpan'), [
            'slot'      => 2,
            'nama_bank' => 'BANK UJIAN VIEWER',
            'no_akaun'  => '999999',
            'coa_id'    => $this->coaId('250-05010'),
            'status'    => 'AKTIF',
        ])->assertForbidden();

        $this->assertDatabaseMissing('bank_account', ['nama_bank' => 'BANK UJIAN VIEWER']);
    }

    public function test_carian_semak_elaun_padan_kod(): void
    {
        $this->actingAs($this->bendahari)
            ->get(route('tetapan.semak', ['search' => 'elaun']))
            ->assertOk()
            ->assertSee('600-10050');
    }
}

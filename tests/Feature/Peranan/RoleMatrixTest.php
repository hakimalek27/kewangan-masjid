<?php

namespace Tests\Feature\Peranan;

use App\Models\AppUser;
use App\Services\Lanjutan\ApprovalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Matriks peranan model baharu: ADMIN = sistem (TIADA tulis kewangan, BUKAN pelulus);
 * BENDAHARI = maker; PENGERUSI = checker; SETIAUSAHA = tulis daftar bukan-kewangan;
 * JURUAUDIT = baca penuh + jejak audit, sifar tulis.
 */
class RoleMatrixTest extends TestCase
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
            'masjid_id' => config('sppkms.masjid_id'), 'login' => 'rm_'.$role.'_'.uniqid(),
            'nama_penuh' => 'Uji '.$role, 'role' => $role,
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
    }

    public function test_admin_tiada_tulis_kewangan_atau_tetapan_masjid(): void
    {
        $admin = $this->buat('admin');

        // Tulis kewangan + tetapan aras-masjid → 403 untuk admin (sistem sahaja).
        foreach (['kutipan.simpan', 'belanja.simpan', 'belanjawan.simpan', 'dana.simpan',
                  'kawalan.simpan', 'tetapan.masjid.kemaskini'] as $rt) {
            $this->actingAs($admin)->post(route($rt), [])->assertForbidden();
        }

        // Tetapi admin BOLEH baca halaman + Konsol Sistem.
        $this->actingAs($admin)->get(route('kawalan.index'))->assertOk();
        $this->actingAs($admin)->get(route('sistem.console'))->assertOk();
    }

    public function test_admin_bukan_pelulus_pengerusi_pelulus(): void
    {
        $admin = $this->buat('admin');
        $pengerusi = $this->buat('pengerusi');

        // Cipta permohonan PENDING sebenar supaya ikatan {approval} berjaya → pagar peranan yang menentukan.
        $approval = app(ApprovalService::class)->mohon('BAYARAN', 500.0, [
            'pemohon' => 'UJIAN', 'coa_id' => $this->coaId('600-06000'), 'jumlah' => '500',
            'cara_bayar' => 'EFT', 'tar_mohon' => '2026-06-12', 'tar_lulus' => '2026-06-12', 'auto_baucer' => '1',
        ]);

        // Admin BUKAN pelulus → 403; tetapi boleh LIHAT senarai kelulusan.
        $this->actingAs($admin)->post(route('kelulusan.lulus', $approval->id))->assertForbidden();
        $this->actingAs($admin)->get(route('kelulusan.index'))->assertOk();

        // Pengerusi = pelulus → bukan 403 (lulus dimainkan; sekurang-kurangnya lepas pagar peranan).
        $this->assertNotSame(403, $this->actingAs($pengerusi)->post(route('kelulusan.lulus', $approval->id))->status());
    }

    public function test_pentadbir_masjid_urus_tetapan_tiada_rekod_kewangan(): void
    {
        $p = $this->buat('pentadbir');

        // Pentadbir Masjid TIDAK merekod kewangan → 403.
        $this->actingAs($p)->post(route('kutipan.simpan'), [])->assertForbidden();
        $this->actingAs($p)->post(route('belanja.simpan'), [])->assertForbidden();

        // BOLEH urus tetapan masjid (lepas pagar peranan → validasi 302, bukan 403).
        $this->assertNotSame(403, $this->actingAs($p)->post(route('bank.simpan'), [])->status());
        $this->assertNotSame(403, $this->actingAs($p)->post(route('kawalan.simpan'), [])->status());
        $this->assertNotSame(403, $this->actingAs($p)->post(route('tetapan.masjid.kemaskini'), [])->status());
        $this->actingAs($p)->get(route('tetapan.pengguna'))->assertOk();

        // BUKAN sistem: Konsol Sistem & onboarding masjid → 403.
        $this->actingAs($p)->get(route('sistem.console'))->assertForbidden();
        $this->actingAs($p)->get(route('tetapan.masjid.baru'))->assertForbidden();
    }

    public function test_juruaudit_baca_audit_tiada_tulis(): void
    {
        $ja = $this->buat('juruaudit');

        $this->actingAs($ja)->get(route('admin.audit'))->assertOk();        // baca jejak audit
        $this->actingAs($ja)->post(route('admin.audit.sahkan'), [])->assertForbidden(); // pengesahan = admin
        $this->actingAs($ja)->post(route('kutipan.simpan'), [])->assertForbidden();      // sifar tulis
    }

    public function test_setiausaha_tulis_daftar_bukan_kewangan_sahaja(): void
    {
        $su = $this->buat('setiausaha');

        // Tulis kewangan → 403.
        $this->actingAs($su)->post(route('kutipan.simpan'), [])->assertForbidden();
        // Daftar bukan-kewangan (sewa) → LEPAS pagar peranan (bukan 403; validasi kosong → 302).
        $this->assertNotSame(403, $this->actingAs($su)->post(route('sewa.simpan'), [])->status());
        // Boleh edit info masjid (bendahari+setiausaha) — bukan 403.
        $this->assertNotSame(403, $this->actingAs($su)->post(route('tetapan.masjid.kemaskini'), [])->status());
    }
}

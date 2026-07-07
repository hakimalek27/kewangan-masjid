<?php

namespace Tests\Feature\Peranan;

use App\Models\AppUser;
use App\Services\Lanjutan\ApprovalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Matriks peranan: ADMIN = superadmin (akses PENUH baca+tulis semua tenant,
 * tulis melalui pagar bukan-admin direkod sebagai ADMIN_OVERRIDE);
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
            'masjid_id' => config('spkm.masjid_id'), 'login' => 'rm_'.$role.'_'.uniqid(),
            'nama_penuh' => 'Uji '.$role, 'role' => $role,
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
    }

    public function test_admin_akses_penuh_tulis_kewangan_dan_tetapan_masjid(): void
    {
        $admin = $this->buat('admin');

        // Superadmin LEPAS semua pagar peranan (payload kosong → 302 validasi, BUKAN 403).
        foreach (['kutipan.simpan', 'belanja.simpan', 'belanjawan.simpan', 'dana.simpan',
                  'kawalan.simpan', 'tetapan.masjid.kemaskini'] as $rt) {
            $this->assertNotSame(403, $this->actingAs($admin)->post(route($rt), [])->status(), $rt);
        }

        // Baca + Konsol Sistem kekal.
        $this->actingAs($admin)->get(route('kawalan.index'))->assertOk();
        $this->actingAs($admin)->get(route('sistem.console'))->assertOk();

        // Tulis melalui pagar bukan-admin direkodkan sebagai ADMIN_OVERRIDE.
        $this->assertTrue(
            \App\Models\SecurityEvent::withoutMasjidScope()
                ->where('jenis', 'ADMIN_OVERRIDE')->exists(),
            'ADMIN_OVERRIDE security_event tidak direkodkan'
        );
    }

    public function test_admin_juga_pelulus_dan_pengerusi_pelulus(): void
    {
        $admin = $this->buat('admin');
        $pengerusi = $this->buat('pengerusi');

        // Cipta permohonan PENDING sebenar supaya ikatan {approval} berjaya → pagar peranan yang menentukan.
        $approval = app(ApprovalService::class)->mohon('BAYARAN', 500.0, [
            'pemohon' => 'UJIAN', 'coa_id' => $this->coaId('600-06000'), 'jumlah' => '500',
            'cara_bayar' => 'EFT', 'tar_mohon' => '2026-06-12', 'tar_lulus' => '2026-06-12', 'auto_baucer' => '1',
        ]);

        // Pengerusi = pelulus → bukan 403 (lepas pagar peranan).
        $this->assertNotSame(403, $this->actingAs($pengerusi)->post(route('kelulusan.lulus', $approval->id))->status());

        // Superadmin JUGA lepas pagar pengerusi (akses penuh) + boleh lihat senarai.
        $approval2 = app(ApprovalService::class)->mohon('BAYARAN', 600.0, [
            'pemohon' => 'UJIAN2', 'coa_id' => $this->coaId('600-06000'), 'jumlah' => '600',
            'cara_bayar' => 'EFT', 'tar_mohon' => '2026-06-12', 'tar_lulus' => '2026-06-12', 'auto_baucer' => '1',
        ]);
        $this->assertNotSame(403, $this->actingAs($admin)->post(route('kelulusan.lulus', $approval2->id))->status());
        $this->actingAs($admin)->get(route('kelulusan.index'))->assertOk();
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

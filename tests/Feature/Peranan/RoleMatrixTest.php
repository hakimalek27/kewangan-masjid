<?php

namespace Tests\Feature\Peranan;

use App\Models\AppUser;
use App\Services\Lanjutan\ApprovalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Matriks peranan: ADMIN = superadmin/PENYEDIA (baca penuh semua tenant + fungsi
 * sistem, TETAPI tiada tulis kewangan/tetapan tenant — cubaan tulis direkod
 * sebagai PERMISSION_DENIED); BENDAHARI = maker; PENGERUSI = checker;
 * SETIAUSAHA = tulis daftar bukan-kewangan; JURUAUDIT = baca penuh + jejak audit,
 * sifar tulis.
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

    public function test_admin_mod_penyedia_dialih_ke_konsol(): void
    {
        $admin = $this->buat('admin');

        // MOD PENYEDIA (belum "Masuk"): laluan kewangan tenant dialih ke Konsol Sistem.
        foreach (['dashboard', 'kutipan.baru', 'kawalan.index', 'penyata.bulanan'] as $rt) {
            $this->actingAs($admin)->get(route($rt))->assertRedirect(route('sistem.console'));
        }

        // Laluan peringkat-penyedia kekal boleh dicapai.
        $this->actingAs($admin)->get(route('sistem.console'))->assertOk();
        $this->actingAs($admin)->get(route('tetapan.pengguna'))->assertOk();
    }

    public function test_admin_provider_baca_sahaja_tiada_tulis_kewangan_tenant(): void
    {
        $admin = $this->buat('admin');
        $this->adminMasuk($admin); // "Masuk" masjid → mod dalam-tenant

        // Superadmin = PENYEDIA: tulis kewangan/tetapan tenant DIHALANG (403).
        foreach (['kutipan.simpan', 'belanja.simpan', 'belanjawan.simpan', 'dana.simpan',
                  'kawalan.simpan', 'tetapan.masjid.kemaskini', 'bank.simpan'] as $rt) {
            $this->actingAs($admin)->post(route($rt), [])->assertForbidden();
        }

        // Baca penuh + Konsol Sistem kekal.
        $this->actingAs($admin)->get(route('kawalan.index'))->assertOk();
        $this->actingAs($admin)->get(route('sistem.console'))->assertOk();

        // Cubaan tulis tenant direkod sebagai PERMISSION_DENIED (jejak keselamatan).
        $this->assertTrue(
            \App\Models\SecurityEvent::withoutMasjidScope()
                ->where('jenis', 'PERMISSION_DENIED')->exists(),
            'PERMISSION_DENIED security_event tidak direkodkan untuk cubaan tulis admin'
        );
    }

    public function test_admin_bukan_pelulus_pengerusi_pelulus(): void
    {
        $admin = $this->buat('admin');
        $this->adminMasuk($admin); // "Masuk" masjid → mod dalam-tenant
        $pengerusi = $this->buat('pengerusi');

        // Cipta permohonan PENDING sebenar supaya ikatan {approval} berjaya → pagar peranan yang menentukan.
        $approval = app(ApprovalService::class)->mohon('BAYARAN', 500.0, [
            'pemohon' => 'UJIAN', 'coa_id' => $this->coaId('600-06000'), 'jumlah' => '500',
            'cara_bayar' => 'EFT', 'tar_mohon' => '2026-06-12', 'tar_lulus' => '2026-06-12', 'auto_baucer' => '1',
        ]);

        // Pengerusi = pelulus → bukan 403 (lepas pagar peranan).
        $this->assertNotSame(403, $this->actingAs($pengerusi)->post(route('kelulusan.lulus', $approval->id))->status());

        // Superadmin (penyedia) BUKAN pelulus (pengasingan tugas) → 403,
        // tetapi BOLEH lihat senarai kelulusan (baca).
        $approval2 = app(ApprovalService::class)->mohon('BAYARAN', 600.0, [
            'pemohon' => 'UJIAN2', 'coa_id' => $this->coaId('600-06000'), 'jumlah' => '600',
            'cara_bayar' => 'EFT', 'tar_mohon' => '2026-06-12', 'tar_lulus' => '2026-06-12', 'auto_baucer' => '1',
        ]);
        $this->actingAs($admin)->post(route('kelulusan.lulus', $approval2->id))->assertForbidden();
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
        // Urus pengguna kini PENYEDIA sahaja → pentadbir tenant disekat.
        $this->actingAs($p)->get(route('tetapan.pengguna'))->assertForbidden();

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

    /** Model SaaS (8 Jul 2026): viewer (JAWI/MAIWP) = penyata + laporan + statistik, BACA sahaja. */
    public function test_viewer_laporan_penuh_baca_sahaja(): void
    {
        $v = $this->buat('viewer');

        // BOLEH baca: penyata + laporan perakaunan + statistik.
        foreach (['penyata.bulanan', 'akaun.untungrugi', 'akaun.kunci', 'akaun.imbangan',
                  'akaun.lejer', 'akaun.program', 'statistik.kutipan', 'statistik.belanja'] as $rt) {
            $this->actingAs($v)->get(route($rt))->assertOk();
        }

        // Halaman BUKAN-laporan → dialih ke penyata (deny-by-default).
        $this->actingAs($v)->get(route('kutipan.senarai'))->assertRedirect(route('penyata.bulanan'));
        $this->actingAs($v)->get(route('dashboard'))->assertRedirect(route('penyata.bulanan'));

        // Sifar tulis.
        $this->actingAs($v)->post(route('kutipan.simpan'), [])->assertForbidden();
    }

    /** Model SaaS: peranan legasi (pentadbir/pengerusi/setiausaha) tidak lagi ditawarkan untuk akaun BAHARU. */
    public function test_peranan_legasi_tidak_ditawarkan_akaun_baharu(): void
    {
        $admin = $this->buat('admin');

        foreach (['pentadbir', 'pengerusi', 'setiausaha'] as $legasi) {
            $this->actingAs($admin)->post(route('tetapan.pengguna.simpan'), [
                'login' => 'lg_'.uniqid(), 'nama_penuh' => 'Legasi', 'role' => $legasi,
                'masjid_id' => config('spkm.masjid_id'), 'kata_laluan' => 'rahsia123',
            ])->assertSessionHasErrors('role');
        }

        // Peranan ditawarkan MASIH boleh dicipta.
        $this->actingAs($admin)->post(route('tetapan.pengguna.simpan'), [
            'login' => 'ok_'.uniqid(), 'nama_penuh' => 'Juruaudit Baharu', 'role' => 'juruaudit',
            'masjid_id' => config('spkm.masjid_id'), 'kata_laluan' => 'rahsia123',
        ])->assertSessionHasNoErrors();
    }
}

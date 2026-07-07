<?php

namespace Tests\Feature\MultiMasjid;

use App\Models\AppUser;
use App\Models\Masjid;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Phase A — akses merentas masjid: senarai boleh-capai, penukar masjid (kebenaran),
 * pemerhati = penyata sahaja. Pelbagai-penyewa (multi-tenant) satu deployment.
 */
class MultiMasjidTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private int $home;

    private int $masjidLain;

    private AppUser $admin;

    private AppUser $bendahari;

    private AppUser $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();

        $this->home = (int) config('spkm.masjid_id');
        $this->masjidLain = (int) Masjid::create(['nama' => 'Masjid Lain '.uniqid()])->id;

        $this->admin = $this->buatUser('admin');
        $this->bendahari = $this->buatUser('bendahari');
        $this->viewer = $this->buatUser('viewer');
        $this->viewer->masjids()->attach($this->masjidLain); // ditugaskan masjid lain
    }

    private function buatUser(string $role): AppUser
    {
        return AppUser::create([
            'masjid_id' => $this->home, 'login' => 'uji_'.$role.'_'.uniqid(),
            'nama_penuh' => 'Uji '.$role, 'role' => $role,
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
    }

    public function test_senarai_masjid_boleh_capai(): void
    {
        $this->assertEqualsCanonicalizing([$this->home], $this->bendahari->accessibleMasjidIds());
        $this->assertEqualsCanonicalizing([$this->home, $this->masjidLain], $this->viewer->accessibleMasjidIds());
        // Admin = semua masjid
        $this->assertContains($this->home, $this->admin->accessibleMasjidIds());
        $this->assertContains($this->masjidLain, $this->admin->accessibleMasjidIds());
    }

    public function test_admin_boleh_tukar_masjid(): void
    {
        $this->actingAs($this->admin)->post(route('masjid.tukar'), ['masjid_id' => $this->masjidLain])
            ->assertRedirect()
            ->assertSessionHas('selected_masjid_id', $this->masjidLain);
    }

    public function test_bendahari_tidak_boleh_tukar_ke_masjid_asing(): void
    {
        $this->actingAs($this->bendahari)->post(route('masjid.tukar'), ['masjid_id' => $this->masjidLain])
            ->assertForbidden();
    }

    public function test_pemerhati_hanya_boleh_penyata(): void
    {
        // Dashboard & halaman lain → dialih ke penyata bulanan
        $this->actingAs($this->viewer)->get(route('dashboard'))->assertRedirect(route('penyata.bulanan'));
        $this->actingAs($this->viewer)->get(route('kutipan.baru'))->assertRedirect(route('penyata.bulanan'));
        // Penyata dibenarkan
        $this->actingAs($this->viewer)->get(route('penyata.bulanan', ['bln' => 1, 'year' => 2026]))->assertOk();
        $this->actingAs($this->viewer)->get(route('penyata.tahunan', ['year' => 2025]))->assertOk();
    }

    public function test_pemerhati_tukar_hanya_masjid_ditugaskan(): void
    {
        // Masjid ditugaskan → boleh
        $this->actingAs($this->viewer)->post(route('masjid.tukar'), ['masjid_id' => $this->masjidLain])
            ->assertRedirect()->assertSessionHas('selected_masjid_id', $this->masjidLain);

        // Masjid TIDAK ditugaskan → 403
        $masjid3 = (int) Masjid::create(['nama' => 'Masjid Tiga '.uniqid()])->id;
        $this->actingAs($this->viewer)->post(route('masjid.tukar'), ['masjid_id' => $masjid3])
            ->assertForbidden();
    }
}

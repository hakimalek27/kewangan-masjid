<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\FixedAsset;
use App\Services\Transaksi\AsetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Butiran Aset (baca sahaja) — semua peranan boleh lihat; auto skop masjid.
 */
class AsetLihatTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private AppUser $bendahari;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();

        $this->bendahari = AppUser::create([
            'masjid_id'     => config('spkm.masjid_id'),
            'login'         => 'uji_asetlihat_'.uniqid(),
            'nama_penuh'    => 'Ujian Aset Lihat',
            'role'          => 'bendahari',
            'password_hash' => Hash::make('rahsia123'),
            'is_active'     => 1,
        ]);
    }

    public function test_butiran_aset_dipapar(): void
    {
        $aset = app(AsetService::class)->registerOpening([
            'nama'             => 'UJIAN ALMARI BESI',
            'coa_id'           => $this->coaId('200-01030'),
            'tarikh_perolehan' => '2026-01-10',
            'kos'              => '1500.00',
            'lokasi'           => 'Pejabat Masjid',
        ]);

        $this->actingAs($this->bendahari)
            ->get(route('aset.lihat', $aset))
            ->assertOk()
            ->assertSee($aset->kod_aset)
            ->assertSee('UJIAN ALMARI BESI')
            ->assertSee('Butiran Aset');
    }
}

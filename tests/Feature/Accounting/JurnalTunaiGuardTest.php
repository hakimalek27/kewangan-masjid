<?php

namespace Tests\Feature\Accounting;

use App\Models\AppUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * C6 — jurnal manual TIDAK boleh menyentuh akaun tunai (250-05xx bank / 250-06xx PWR).
 */
class JurnalTunaiGuardTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
    }

    private function bendahari(): AppUser
    {
        return AppUser::create([
            'masjid_id' => config('spkm.masjid_id'), 'login' => 'jg_'.uniqid(),
            'nama_penuh' => 'Bendahari', 'role' => 'bendahari',
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
    }

    public function test_jurnal_kredit_akaun_bank_ditolak(): void
    {
        $this->actingAs($this->bendahari())
            ->post(route('belanja.jurnal.simpan'), [
                'tarikh'    => '2026-06-10',
                'deskripsi' => 'Ujian jurnal salah',
                'dr_coa_id' => $this->coaId('600-06000'),
                'cr_coa_id' => $this->coaId('250-05010'), // akaun BANK — tidak dibenarkan
                'jumlah'    => '100.00',
            ])
            ->assertSessionHasErrors('cr_coa_id');
    }

    public function test_jurnal_bukan_tunai_diterima(): void
    {
        $this->actingAs($this->bendahari())
            ->post(route('belanja.jurnal.simpan'), [
                'tarikh'    => '2026-06-10',
                'deskripsi' => 'Ujian jurnal sah',
                'dr_coa_id' => $this->coaId('600-06000'),
                'cr_coa_id' => $this->coaId('300-04020'), // liabiliti — dibenarkan
                'jumlah'    => '100.00',
            ])
            ->assertSessionHasNoErrors();
    }
}

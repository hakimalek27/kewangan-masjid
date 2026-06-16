<?php

namespace Tests\Feature\Validasi;

use App\Models\AppUser;
use App\Models\BankAccount;
use App\Models\Coa;
use App\Models\Masjid;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Validasi FK diskop-masjid (MasjidRule / existsMasjid): rujukan COA/bank milik
 * masjid LAIN mesti ditolak; rujukan masjid sendiri lulus.
 */
class ValidasiSkopMasjidTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private AppUser $bendahari;

    private int $masjidLain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();

        $this->bendahari = AppUser::create([
            'masjid_id' => config('sppkms.masjid_id'), 'login' => 'uji_skop_'.uniqid(),
            'nama_penuh' => 'Ujian Skop', 'role' => 'bendahari',
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);

        // Masjid KEDUA yang sah (FK coa.masjid_id memerlukan masjid wujud)
        $this->masjidLain = (int) Masjid::create(['nama' => 'Masjid Ujian Skop '.uniqid()])->id;
    }

    /** Cipta klon rekod milik masjid lain (masjid_id berbeza) untuk diuji ditolak. */
    private function rekodMasjidLain(string $model, array $cari, array $ubah)
    {
        $asal = $model::withoutMasjidScope()
            ->where('masjid_id', config('sppkms.masjid_id'))
            ->where($cari)->firstOrFail();
        $klon = $asal->replicate();
        $klon->masjid_id = $this->masjidLain;
        foreach ($ubah as $k => $v) {
            $klon->{$k} = $v;
        }
        $klon->save();

        return $klon;
    }

    public function test_coa_masjid_lain_ditolak(): void
    {
        $asing = $this->rekodMasjidLain(Coa::class, ['kod' => '400-03010'], ['kod' => '400-99999']);

        $this->actingAs($this->bendahari)->post(route('kutipan.simpan'), [
            'coa_id' => $asing->id, 'kaedah' => 'TUNAI', 'tarikh' => '2026-06-30',
            'jumlah' => '10.00', 'auto_resit' => '1', 'semakan' => '1',
        ])->assertSessionHasErrors('coa_id');
    }

    public function test_coa_masjid_sendiri_lulus(): void
    {
        $this->actingAs($this->bendahari)->post(route('kutipan.simpan'), [
            'coa_id' => $this->coaId('400-03010'), 'kaedah' => 'TUNAI', 'tarikh' => '2026-06-30',
            'jumlah' => '10.00', 'auto_resit' => '1', 'semakan' => '1',
        ])->assertSessionHasNoErrors();
    }

    public function test_bank_masjid_lain_ditolak(): void
    {
        $asingBank = $this->rekodMasjidLain(BankAccount::class, [], []);

        $this->actingAs($this->bendahari)->post(route('kutipan.simpan'), [
            'coa_id' => $this->coaId('400-03010'), 'kaedah' => 'BANK_TRANSFER_QR', 'tarikh' => '2026-06-30',
            'jumlah' => '10.00', 'auto_resit' => '1', 'bank_account_id' => $asingBank->id, 'semakan' => '1',
        ])->assertSessionHasErrors('bank_account_id');
    }
}

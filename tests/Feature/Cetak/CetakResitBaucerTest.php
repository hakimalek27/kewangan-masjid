<?php

namespace Tests\Feature\Cetak;

use App\Models\AppUser;
use App\Models\BankAccount;
use App\Models\Kutipan;
use App\Models\Masjid;
use App\Models\Pembayaran;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Cetakan resit & baucer format A4 — kepala masjid, terbilang, mod
 * SIGNATURE/DISCLAIMER, dan pilihan 2 salinan / 1 A4 untuk resit.
 */
class CetakResitBaucerTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private AppUser $bendahari;
    private BankAccount $bank;
    private string $namaMasjid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();

        $this->bendahari = AppUser::create([
            'masjid_id' => config('spkm.masjid_id'), 'login' => 'uji_cetak_'.uniqid(),
            'nama_penuh' => 'Ujian Cetak', 'role' => 'bendahari',
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
        $this->bank = BankAccount::withoutMasjidScope()
            ->where('masjid_id', config('spkm.masjid_id'))->firstOrFail();
        $this->namaMasjid = Masjid::find((int) config('spkm.masjid_id'))->nama;
    }

    private function buatKutipan(string $noResit = 'UJI-CETAK-1'): Kutipan
    {
        $this->actingAs($this->bendahari)->post(route('kutipan.simpan'), [
            'coa_id' => $this->coaId('400-03010'), 'kaedah' => 'TUNAI',
            'tarikh' => '2026-06-12', 'jumlah' => '15.50', 'no_resit' => $noResit,
            'nama_pemberi' => 'PENDERMA UJIAN', 'deskripsi' => 'SUMBANGAN UJIAN', 'semakan' => '1',
        ]);

        return Kutipan::withoutMasjidScope()->where('no_resit', $noResit)->firstOrFail();
    }

    private function buatPembayaran(string $noBaucer = 'UJI-CETAK-PV1'): Pembayaran
    {
        $this->actingAs($this->bendahari)->post(route('belanja.simpan'), [
            'tar_mohon' => '2026-06-12', 'tar_lulus' => '2026-06-12',
            'baucer_no' => $noBaucer, 'pemohon' => 'PEMBEKAL UJIAN',
            'coa_id' => $this->coaId('600-06000'), 'jumlah' => '15.50',
            'cara_bayar' => 'EFT', 'bank_account_id' => $this->bank->id,
            'deskripsi' => 'BAYARAN UJIAN', 'semakan' => '1',
        ]);

        return Pembayaran::withoutMasjidScope()->where('baucer_no', $noBaucer)->firstOrFail();
    }

    public function test_cetak_resit_satu_salinan_signature(): void
    {
        $kutipan = $this->buatKutipan();

        $this->actingAs($this->bendahari)
            ->get(route('kutipan.cetak', $kutipan).'?salinan=1&mod=SIGNATURE')
            ->assertOk()
            ->assertSee('RESIT KUTIPAN')
            ->assertSee($kutipan->no_resit)
            ->assertSee($this->namaMasjid)
            ->assertSee('RINGGIT MALAYSIA')
            ->assertSee('LIMA BELAS DAN LIMA PULUH SEN SAHAJA')
            ->assertSee('COP DAN NAMA')
            ->assertDontSee('TIDAK MEMERLUKAN SEBARANG TANDATANGAN');
    }

    public function test_cetak_resit_dua_salinan_satu_a4(): void
    {
        $kutipan = $this->buatKutipan('UJI-CETAK-2UP');

        $resp = $this->actingAs($this->bendahari)
            ->get(route('kutipan.cetak', $kutipan).'?salinan=2&mod=SIGNATURE')
            ->assertOk()
            ->assertSee('Salinan Pembayar')
            ->assertSee('Salinan Bendahari');

        // No resit muncul pada KEDUA-DUA separuh (tajuk bar alat + 2 badan = >= 3)
        $this->assertGreaterThanOrEqual(3, substr_count($resp->getContent(), $kutipan->no_resit));
    }

    public function test_cetak_resit_papar_slip_tarbankin_saksi(): void
    {
        $noResit = 'UJI-CETAK-SLIP';
        $this->actingAs($this->bendahari)->post(route('kutipan.simpan'), [
            'coa_id' => $this->coaId('400-03010'), 'kaedah' => 'BANK_TRANSFER_QR',
            'tarikh' => '2026-06-12', 'jumlah' => '88.00', 'no_resit' => $noResit,
            'nama_pemberi' => 'PENDERMA UJIAN', 'bank_account_id' => $this->bank->id,
            'no_slip' => 'SLIP-XYZ-123', 'tar_bankin' => '2026-06-13',
            'saksi1' => 'SAKSI SATU', 'saksi2' => 'SAKSI DUA',
            'deskripsi' => 'SUMBANGAN UJIAN', 'semakan' => '1',
        ]);
        $kutipan = Kutipan::withoutMasjidScope()->where('no_resit', $noResit)->firstOrFail();

        $this->actingAs($this->bendahari)
            ->get(route('kutipan.cetak', $kutipan).'?mod=SIGNATURE')
            ->assertOk()
            ->assertSee('SLIP-XYZ-123')
            ->assertSee('13/06/2026')
            ->assertSee('SAKSI SATU')
            ->assertSee('SAKSI DUA');
    }

    public function test_cetak_resit_mod_disclaimer(): void
    {
        $kutipan = $this->buatKutipan('UJI-CETAK-DIS');

        $this->actingAs($this->bendahari)
            ->get(route('kutipan.cetak', $kutipan).'?mod=DISCLAIMER')
            ->assertOk()
            ->assertSee('TIDAK MEMERLUKAN SEBARANG TANDATANGAN')
            ->assertDontSee('COP DAN NAMA');
    }

    public function test_cetak_baucer_signature(): void
    {
        $p = $this->buatPembayaran();

        $this->actingAs($this->bendahari)
            ->get(route('belanja.cetak', $p).'?mod=SIGNATURE')
            ->assertOk()
            ->assertSee('BAUCER BAYARAN')
            ->assertSee($p->baucer_no)
            ->assertSee('PEMBEKAL UJIAN')
            ->assertSee($this->namaMasjid)
            ->assertSee('RINGGIT MALAYSIA')
            ->assertSee('Disediakan Oleh')
            ->assertSee('BENDAHARI')
            ->assertSee('PENGERUSI')
            ->assertSee('PENERIMA');
    }

    public function test_cetak_baucer_mod_disclaimer(): void
    {
        $p = $this->buatPembayaran('UJI-CETAK-PV2');

        $this->actingAs($this->bendahari)
            ->get(route('belanja.cetak', $p).'?mod=DISCLAIMER')
            ->assertOk()
            ->assertSee('TIDAK MEMERLUKAN SEBARANG TANDATANGAN')
            ->assertDontSee('Disediakan Oleh');
    }
}

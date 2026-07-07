<?php

namespace Tests\Feature\Transaksi;

use App\Models\AppUser;
use App\Models\BankAccount;
use App\Models\Kutipan;
use App\Models\Pembayaran;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Ujian HTTP hujung-ke-hujung: borang web → controller → service → jurnal.
 */
class WebFormTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private AppUser $bendahari;
    private AppUser $viewer;
    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();

        $buat = fn (string $role) => AppUser::create([
            'masjid_id' => config('spkm.masjid_id'), 'login' => 'uji_'.$role.'_'.uniqid(),
            'nama_penuh' => 'Ujian '.$role, 'role' => $role,
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
        $this->bendahari = $buat('bendahari');
        $this->viewer = $buat('viewer');
        $this->bank = BankAccount::withoutMasjidScope()->where('masjid_id', config('spkm.masjid_id'))->firstOrFail();
    }

    public function test_borang_kutipan_dipapar(): void
    {
        $this->actingAs($this->bendahari)->get(route('kutipan.baru'))
            ->assertOk()
            ->assertSee('Kutipan');
    }

    public function test_submit_kutipan_cipta_rekod_dan_jurnal(): void
    {
        $resp = $this->actingAs($this->bendahari)->post(route('kutipan.simpan'), [
            'coa_id' => $this->coaId('400-03010'),
            'kaedah' => 'BANK_TRANSFER_QR',
            'tarikh' => '2026-06-12',
            'jumlah' => '15.50',
            'no_resit' => 'UJI-WEB-1',
            'nama_pemberi' => 'UJIAN WEB',
            'bank_account_id' => $this->bank->id,
            'deskripsi' => 'UJIAN BORANG WEB',
            'semakan' => '1',
        ]);

        $kutipan = Kutipan::withoutMasjidScope()->where('no_resit', 'UJI-WEB-1')->first();
        $this->assertNotNull($kutipan, 'Kutipan mesti tercipta');
        $resp->assertRedirect(route('kutipan.view', $kutipan));

        // Jurnal: Dr Bank / Cr 400-03010
        $entries = $kutipan->voucher()->first()->entries;
        $this->assertSame('15.50', (string) $entries->where('coa_id', $this->coaId('250-05010'))->first()->debit);
        $this->assertSame('15.50', (string) $entries->where('coa_id', $this->coaId('400-03010'))->first()->kredit);
    }

    public function test_padam_kutipan_melalui_web(): void
    {
        $this->actingAs($this->bendahari)->post(route('kutipan.simpan'), [
            'coa_id' => $this->coaId('400-03010'), 'kaedah' => 'TUNAI',
            'tarikh' => '2026-06-12', 'jumlah' => '5.00', 'no_resit' => 'UJI-WEB-2',
            'semakan' => '1',
        ]);
        $kutipan = Kutipan::withoutMasjidScope()->where('no_resit', 'UJI-WEB-2')->first();

        $this->actingAs($this->bendahari)
            ->post(route('kutipan.padam', $kutipan))
            ->assertRedirect(route('kutipan.senarai'));

        $this->assertSame('DELETED', $kutipan->fresh()->status);
        $this->assertSame('VOID', $kutipan->voucher()->first()->status);
    }

    public function test_submit_belanja_eft(): void
    {
        $this->actingAs($this->bendahari)->post(route('belanja.simpan'), [
            'tar_mohon' => '2026-06-12', 'tar_lulus' => '2026-06-12',
            'baucer_no' => 'UJI-WEB-PV1', 'pemohon' => 'UJIAN WEB',
            'coa_id' => $this->coaId('600-06000'), 'jumlah' => '20.00',
            'cara_bayar' => 'EFT', 'bank_account_id' => $this->bank->id,
            'deskripsi' => 'UJIAN BELANJA WEB', 'semakan' => '1',
        ]);

        $p = Pembayaran::withoutMasjidScope()->where('baucer_no', 'UJI-WEB-PV1')->first();
        $this->assertNotNull($p);
        $this->assertSame('BAYARAN', $p->jenis);
        $this->assertNotNull($p->voucher_id);
    }

    public function test_submit_rekupmen(): void
    {
        $this->actingAs($this->bendahari)->post(route('belanja.rekupmen.simpan'), [
            'tar_mohon' => '2026-06-12', 'tar_lulus' => '2026-06-12',
            'baucer_no' => 'UJI-WEB-RK1', 'pwr_coa_id' => $this->coaId('250-06010'),
            'bank_account_id' => $this->bank->id, 'jumlah' => '10.00',
            'pemohon' => 'UJIAN', 'deskripsi' => 'UJIAN REKUPMEN WEB', 'cara_bayar' => 'EFT',
            'semakan' => '1',
        ]);

        $p = Pembayaran::withoutMasjidScope()->where('baucer_no', 'UJI-WEB-RK1')->first();
        $this->assertNotNull($p);
        $this->assertSame('REKUPMEN', $p->jenis);
    }

    public function test_viewer_tidak_boleh_menulis(): void
    {
        $this->actingAs($this->viewer)->post(route('kutipan.simpan'), [
            'coa_id' => $this->coaId('400-03010'), 'kaedah' => 'TUNAI',
            'tarikh' => '2026-06-12', 'jumlah' => '1.00', 'no_resit' => 'UJI-VIEWER',
            'semakan' => '1',
        ])->assertForbidden();

        $this->assertNull(Kutipan::withoutMasjidScope()->where('no_resit', 'UJI-VIEWER')->first());
    }

    public function test_viewer_dialih_dari_senarai_ke_penyata(): void
    {
        // Pemerhati (Phase A) = penyata sahaja → senarai kutipan dialih ke penyata bulanan
        $this->actingAs($this->viewer)->get(route('kutipan.senarai'))->assertRedirect(route('penyata.bulanan'));
    }

    public function test_medan_wajib_disekat(): void
    {
        $this->actingAs($this->bendahari)
            ->from(route('kutipan.baru'))
            ->post(route('kutipan.simpan'), ['jumlah' => '1.00'])
            ->assertRedirect(route('kutipan.baru'))
            ->assertSessionHasErrors(['coa_id', 'kaedah', 'tarikh']);
    }
}

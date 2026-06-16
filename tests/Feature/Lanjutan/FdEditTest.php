<?php

namespace Tests\Feature\Lanjutan;

use App\Models\AppUser;
use App\Models\BankAccount;
use App\Models\FdInvestment;
use App\Services\Transaksi\FdService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Edit Pelaburan FD — hanya medan BUKAN-kewangan boleh diubah; jumlah, COA FD
 * & COA bank dikekalkan kerana ia menjejaskan jurnal yang sudah POSTED.
 */
class FdEditTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private AppUser $bendahari;
    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();

        $this->bendahari = AppUser::create([
            'masjid_id'     => config('sppkms.masjid_id'),
            'login'         => 'uji_fdedit_'.uniqid(),
            'nama_penuh'    => 'Ujian FD Edit',
            'role'          => 'bendahari',
            'password_hash' => Hash::make('rahsia123'),
            'is_active'     => 1,
        ]);

        $this->bank = BankAccount::withoutMasjidScope()
            ->where('masjid_id', config('sppkms.masjid_id'))
            ->where('status', 'AKTIF')->firstOrFail();
    }

    private function buatFd(): FdInvestment
    {
        return app(FdService::class)->create([
            'tarikh'      => '2026-06-01',
            'institusi'   => 'BANK ASAL',
            'coa_fd_id'   => $this->coaId('250-04010'),
            'coa_bank_id' => (int) $this->bank->coa_id,
            'jumlah'      => '5000.00',
            'no_sijil'    => 'SIJIL-001',
        ]);
    }

    public function test_borang_edit_fd_dipapar(): void
    {
        $fd = $this->buatFd();

        $this->actingAs($this->bendahari)
            ->get(route('fd.edit', $fd))
            ->assertOk()
            ->assertSee('BANK ASAL')
            ->assertSee('Edit Pelaburan FD');
    }

    public function test_kemaskini_ubah_medan_bukan_kewangan(): void
    {
        $fd = $this->buatFd();

        $this->actingAs($this->bendahari)
            ->post(route('fd.kemaskini', $fd), [
                'institusi'     => 'BANK BAHARU',
                'no_sijil'      => 'SIJIL-999',
                'kadar_pct'     => '3.50',
                'tempoh_bulan'  => '12',
                'maturity_date' => '2027-06-01',
                'keterangan'    => 'Dikemaskini melalui ujian',
                // Cuba selitkan medan kewangan — MESTI diabaikan
                'jumlah'        => '999999.99',
                'coa_fd_id'     => $this->coaId('250-04020'),
                'coa_bank_id'   => 12345,
            ])
            ->assertRedirect(route('fd.senarai'));

        $this->assertDatabaseHas('fd_investment', [
            'id'        => $fd->id,
            'institusi' => 'BANK BAHARU',
            'no_sijil'  => 'SIJIL-999',
        ]);

        // Medan kewangan TIDAK berubah walau dihantar
        $fresh = $fd->fresh();
        $this->assertSame('5000.00', (string) $fresh->jumlah, 'Jumlah TIDAK boleh berubah');
        $this->assertSame((int) $this->coaId('250-04010'), (int) $fresh->coa_fd_id, 'COA FD TIDAK boleh berubah');
        $this->assertSame((int) $this->bank->coa_id, (int) $fresh->coa_bank_id, 'COA bank TIDAK boleh berubah');
    }
}

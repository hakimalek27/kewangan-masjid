<?php

namespace Tests\Feature\Keselamatan;

use App\Models\AppUser;
use App\Models\Attachment;
use App\Models\BankAccount;
use App\Models\Pembayaran;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Lampiran perbelanjaan (ber-PII) MESTI disimpan PRIVATE & dihidang berpagar-auth
 * dengan jenis fail terhad — penemuan audit #1.
 */
class LampiranTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private AppUser $bendahari;
    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
        $this->bendahari = AppUser::create([
            'masjid_id' => config('spkm.masjid_id'), 'login' => 'uji_lamp_'.uniqid(),
            'nama_penuh' => 'Bendahari', 'role' => 'bendahari',
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
        $this->bank = BankAccount::withoutMasjidScope()->where('masjid_id', config('spkm.masjid_id'))->firstOrFail();
    }

    public function test_lampiran_disimpan_private_bukan_awam(): void
    {
        $this->actingAs($this->bendahari)->post(route('belanja.simpan'), [
            'tar_mohon' => '2026-06-12', 'tar_lulus' => '2026-06-12',
            'baucer_no' => 'LAMP-1', 'pemohon' => 'UJIAN', 'coa_id' => $this->coaId('600-06000'),
            'jumlah' => '10.00', 'cara_bayar' => 'EFT', 'bank_account_id' => $this->bank->id,
            'deskripsi' => 'UJIAN LAMPIRAN', 'semakan' => '1',
            'dokumen' => [UploadedFile::fake()->create('invois.pdf', 20, 'application/pdf')],
        ])->assertRedirect();

        $att = Attachment::withoutMasjidScope()->where('file_name', 'invois.pdf')->latest('id')->first();
        $this->assertNotNull($att);
        // Disimpan di disk PRIVATE (storage/app/private/lampiran), BUKAN public
        $this->assertStringStartsWith('lampiran/', $att->file_path);
        $this->assertTrue(Storage::disk('local')->exists($att->file_path));
        $this->assertFalse(Storage::disk('public')->exists($att->file_path),
            'Lampiran PII TIDAK boleh berada di storan awam.');
    }

    public function test_jenis_fail_berbahaya_ditolak(): void
    {
        $this->actingAs($this->bendahari)->from(route('belanja.baru'))->post(route('belanja.simpan'), [
            'tar_mohon' => '2026-06-12', 'tar_lulus' => '2026-06-12',
            'baucer_no' => 'LAMP-2', 'pemohon' => 'UJIAN', 'coa_id' => $this->coaId('600-06000'),
            'jumlah' => '10.00', 'cara_bayar' => 'EFT', 'bank_account_id' => $this->bank->id,
            'deskripsi' => 'UJIAN', 'semakan' => '1',
            'dokumen' => [UploadedFile::fake()->create('jahat.html', 5, 'text/html')],
        ])->assertSessionHasErrors('dokumen.0');
    }

    public function test_lampiran_perlu_auth_dan_hidang_dengan_nosniff(): void
    {
        // Cipta pembayaran + lampiran private
        $p = Pembayaran::withoutMasjidScope()->create([
            'masjid_id' => config('spkm.masjid_id'), 'jenis' => 'BAYARAN',
            'tar_lulus' => '2026-06-12', 'period_ym' => '2026-06', 'coa_id' => $this->coaId('600-06000'),
            'jumlah' => '1.00', 'cara_bayar' => 'EFT', 'baucer_no' => 'LAMP-3', 'status' => 'ACTIVE',
        ]);
        Storage::disk('local')->put('lampiran/rahsia.pdf', '%PDF-test');
        $att = Attachment::withoutMasjidScope()->create([
            'masjid_id' => config('spkm.masjid_id'), 'owner_type' => 'BAYARAN', 'owner_id' => $p->id,
            'file_path' => 'lampiran/rahsia.pdf', 'file_name' => 'rahsia.pdf', 'mime' => 'application/pdf',
        ]);

        // Tetamu (belum log masuk) → ke login
        $this->get(route('belanja.lampiran', $att->id))->assertRedirect(route('login'));

        // Bendahari → boleh, dengan header anti-sniff
        $this->actingAs($this->bendahari)->get(route('belanja.lampiran', $att->id))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        Storage::disk('local')->delete('lampiran/rahsia.pdf');
    }
}

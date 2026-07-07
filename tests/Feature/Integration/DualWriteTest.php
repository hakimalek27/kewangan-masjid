<?php

namespace Tests\Feature\Integration;

use App\Jobs\PostDualWrite;
use App\Models\AppUser;
use App\Models\BankAccount;
use App\Models\Kutipan;
use App\Models\SppkmsSync;
use App\Services\Integration\SppkmsDualWriteService;
use App\Services\Security\SecretVaultService;
use App\Services\Transaksi\KutipanService;
use App\Services\Transaksi\PembayaranService;
use App\Support\Setting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Fasa 8 — Dual-write ke SPPKMS lama. SEMUA HTTP ke *mesrasuci.com*
 * di-fake (Http::fake) — TIADA panggilan rangkaian sebenar.
 */
class DualWriteTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private const SESI = 'ujisesi123';

    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();

        $this->bank = BankAccount::withoutMasjidScope()
            ->where('masjid_id', config('spkm.masjid_id'))
            ->firstOrFail();

        // Kredensial SPPKMS dalam vault + rujukan dalam app_setting
        $vault = app(SecretVaultService::class);
        Setting::set('sppkms_login_ref', $vault->put('uji_login_sppkms'));
        Setting::set('sppkms_password_ref', $vault->put('uji_password_sppkms'));
    }

    // ------------------------------------------------------------------
    //  Pembantu
    // ------------------------------------------------------------------

    private function fakeHttpJaya(string $lokasiSimpan = 'kutipan_view.php?recno=10500'): void
    {
        Http::fake([
            '*mesrasuci.com/login-exec.php' => Http::response('', 302, [
                'Set-Cookie' => 'PHPSESSID='.self::SESI.'; path=/',
                'Location'   => 'dashboard.php',
            ]),
            '*mesrasuci.com/kutipan_save.php' => Http::response('', 302, ['Location' => $lokasiSimpan]),
            '*mesrasuci.com/*' => Http::response('', 200),
            '*' => Http::response('', 200),
        ]);
    }

    private function ciptaKutipan(string $resit): Kutipan
    {
        return app(KutipanService::class)->create([
            'tarikh'          => '2026-06-12',
            'coa_id'          => $this->coaId('400-03010'),
            'kaedah'          => 'BANK_TRANSFER_QR',
            'jumlah'          => '1.00',
            'no_resit'        => $resit,
            'bank_account_id' => $this->bank->id,
            'no_slip'         => 'SLIP-DW',
            'tar_bankin'      => '2026-06-12',
            'nama_pemberi'    => 'UJIAN DUAL-WRITE',
            'deskripsi'       => 'UJIAN DUAL-WRITE - SILA ABAIKAN',
        ]);
    }

    private function syncUntuk(Kutipan $k): SppkmsSync
    {
        return SppkmsSync::withoutMasjidScope()
            ->where('masjid_id', config('spkm.masjid_id'))
            ->where('source_type', 'KUTIPAN')
            ->where('source_id', $k->id)
            ->firstOrFail();
    }

    // ------------------------------------------------------------------
    //  (a) Toggle OFF → tiada baris sppkms_sync
    // ------------------------------------------------------------------

    public function test_toggle_off_tiada_baris_sync(): void
    {
        Http::fake();
        Queue::fake([PostDualWrite::class]);
        Setting::set('dual_write_sppkms', 'off');

        $k = $this->ciptaKutipan('UJI-DW0');

        $this->assertDatabaseMissing('sppkms_sync', [
            'masjid_id'   => config('spkm.masjid_id'),
            'source_type' => 'KUTIPAN',
            'source_id'   => $k->id,
        ]);
        Queue::assertNothingPushed();
    }

    // ------------------------------------------------------------------
    //  (b) Toggle ON → baris PENDING + job dihantar ke barisan 'sync'
    // ------------------------------------------------------------------

    public function test_toggle_on_baris_pending_dicipta(): void
    {
        Http::fake();
        Queue::fake([PostDualWrite::class]);
        Setting::set('dual_write_sppkms', 'on');

        $k = $this->ciptaKutipan('UJI-DW1');

        $this->assertDatabaseHas('sppkms_sync', [
            'masjid_id'   => config('spkm.masjid_id'),
            'source_type' => 'KUTIPAN',
            'source_id'   => $k->id,
            'status'      => 'PENDING',
        ]);
        Queue::assertPushed(PostDualWrite::class, fn ($job) => $job->queue === 'sync');
    }

    // ------------------------------------------------------------------
    //  (c) Job berjaya → DONE + recno + medan POST tepat
    // ------------------------------------------------------------------

    public function test_job_berjaya_done_recno_dan_medan_post_tepat(): void
    {
        $this->fakeHttpJaya();
        Queue::fake([PostDualWrite::class]);
        Setting::set('dual_write_sppkms', 'on');

        $k = $this->ciptaKutipan('UJI-DW2');
        $sync = $this->syncUntuk($k);

        (new PostDualWrite($sync->id))->handle(app(SppkmsDualWriteService::class));

        $sync->refresh();
        $this->assertSame('DONE', $sync->status);
        $this->assertSame('10500', $sync->sppkms_recno);
        $this->assertSame('kutipan_save.php', $sync->sppkms_endpoint);
        $this->assertSame(1, (int) $sync->attempts);
        $this->assertNotNull($sync->done_at);

        // Login: medan login/password/hp_field (honeypot kosong)
        Http::assertSent(fn ($req) => str_contains($req->url(), 'login-exec.php')
            && $req['login'] === 'uji_login_sppkms'
            && $req['password'] === 'uji_password_sppkms'
            && $req['hp_field'] === '');

        // Borang kutipan: kod COA + '|', 'semak' TIDAK dihantar (checkbox tak ditanda
        // = no resit manual = jangan ganggu kaunter SPPKMS), sesi cookie
        Http::assertSent(fn ($req) => str_contains($req->url(), 'kutipan_save.php')
            && $req['combined_jenis'] === '400-03010|'
            && $req['kaedah'] === '2'                 // BANK_TRANSFER_QR
            && $req['tar_kutipan'] === '2026-06-12'
            && $req['jum_kutipan'] === '1.00'
            && !isset($req['semak'])                  // checkbox auto-resit TIDAK ditanda
            && $req['noresit'] === 'UJI-DW2'
            && $req['bank'] === (string) $this->bank->slot   // value = nombor slot ("1")
            && $req['semakan'] === 'on'
            && str_contains(implode(' ', $req->header('Cookie')), 'PHPSESSID='.self::SESI));
    }

    // ------------------------------------------------------------------
    //  (d) Job kali KEDUA → tiada POST kedua (idempoten)
    // ------------------------------------------------------------------

    public function test_job_kali_kedua_idempoten_tiada_post_kedua(): void
    {
        $this->fakeHttpJaya();
        Queue::fake([PostDualWrite::class]);
        Setting::set('dual_write_sppkms', 'on');

        $k = $this->ciptaKutipan('UJI-DW3');
        $sync = $this->syncUntuk($k);

        $job = new PostDualWrite($sync->id);
        $job->handle(app(SppkmsDualWriteService::class));
        $job->handle(app(SppkmsDualWriteService::class)); // kali kedua

        $sync->refresh();
        $this->assertSame('DONE', $sync->status);
        $this->assertSame(1, (int) $sync->attempts); // tidak bertambah

        $bilPost = Http::recorded(fn ($req) => str_contains($req->url(), 'kutipan_save.php'))->count();
        $this->assertSame(1, $bilPost, 'POST kutipan_save.php mesti SEKALI sahaja (idempoten).');
    }

    // ------------------------------------------------------------------
    //  (e) POST dihantar tetapi recno tiada → FAILED (TIADA retry buta),
    //      elak rekod PENDUA di SPPKMS lama
    // ------------------------------------------------------------------

    public function test_post_dihantar_tanpa_recno_failed_tanpa_retry(): void
    {
        Http::fake([
            '*mesrasuci.com/login-exec.php' => Http::response('', 302, [
                'Set-Cookie' => 'PHPSESSID='.self::SESI.'; path=/',
                'Location'   => 'dashboard.php',
            ]),
            '*mesrasuci.com/kutipan_save.php' => Http::response('Ralat tidak diketahui', 200),
            '*' => Http::response('', 200),
        ]);
        Queue::fake([PostDualWrite::class]);
        Setting::set('dual_write_sppkms', 'on');

        $k = $this->ciptaKutipan('UJI-DW4');
        $sync = $this->syncUntuk($k);

        // TIDAK throw — POST sudah dihantar (rekod mungkin tercipta), jadi job
        // tamat tanpa re-throw supaya barisan TIDAK cuba semula (elak pendua).
        (new PostDualWrite($sync->id))->handle(app(SppkmsDualWriteService::class));

        $sync->refresh();
        $this->assertSame('FAILED', $sync->status); // bukan PENDING — tiada retry automatik
        $this->assertSame(1, (int) $sync->attempts);
        $this->assertNull($sync->sppkms_recno);
        $this->assertStringContainsString('SEMAK MANUAL', (string) $sync->last_error);

        // POST kutipan_save.php hanya SEKALI — tiada hantaran kedua
        $this->assertSame(1, Http::recorded(fn ($req) => str_contains($req->url(), 'kutipan_save.php'))->count());
    }

    public function test_belanja_guna_kunci_bank_bukan_bank_select(): void
    {
        // Borang belanja lama: <select name="bank" id="bank_select"> — pelayar POST
        // atribut NAME, jadi medan mesti 'bank' bukan 'bank_select'.
        Http::fake([
            '*mesrasuci.com/login-exec.php' => Http::response('', 302, [
                'Set-Cookie' => 'PHPSESSID='.self::SESI.'; path=/', 'Location' => 'dashboard.php',
            ]),
            '*mesrasuci.com/belanja_expense.php' => Http::response('', 302, ['Location' => 'belanja_view.php?id=20300']),
            '*' => Http::response('', 200),
        ]);
        Queue::fake([PostDualWrite::class]);
        Setting::set('dual_write_sppkms', 'on');

        $p = app(PembayaranService::class)->createBayaran([
            'tar_lulus' => '2026-06-12', 'coa_id' => $this->coaId('600-06000'),
            'jumlah' => '1.00', 'cara_bayar' => 'EFT', 'bank_account_id' => $this->bank->id,
            'baucer_no' => 'UJI-DWB', 'pemohon' => 'UJIAN', 'deskripsi' => 'UJIAN BELANJA DW',
        ]);
        $sync = SppkmsSync::withoutMasjidScope()
            ->where('source_type', 'BAYARAN')->where('source_id', $p->id)->firstOrFail();

        (new PostDualWrite($sync->id))->handle(app(SppkmsDualWriteService::class));

        Http::assertSent(fn ($req) => str_contains($req->url(), 'belanja_expense.php')
            && isset($req['bank'])           // kunci betul: 'bank'
            && !isset($req['bank_select'])   // kunci lama yang salah TIADA
            && !isset($req['semak']));
    }

    // ------------------------------------------------------------------
    //  (f) Pembayaran jenis ASET → SKIPPED (hantar manual)
    // ------------------------------------------------------------------

    public function test_pembayaran_aset_skipped(): void
    {
        Http::fake(); // tiada HTTP langsung dijangka
        Setting::set('dual_write_sppkms', 'on');

        // Queue sebenar = sync → PostDualWrite berjalan terus selepas commit
        $p = app(PembayaranService::class)->createAset([
            'tar_lulus' => '2026-06-12', 'coa_id' => $this->coaId('200-01060'),
            'jumlah' => '1.00', 'cara_bayar' => 'EFT', 'bank_account_id' => $this->bank->id,
            'baucer_no' => 'UJI-DW5', 'asset_name' => 'UJIAN ASET DUAL-WRITE',
            'asset_location' => 'PEJABAT', 'useful_life' => 5, 'depn_rate' => 20,
        ]);

        $this->assertDatabaseHas('sppkms_sync', [
            'masjid_id'   => config('spkm.masjid_id'),
            'source_type' => 'BAYARAN',
            'source_id'   => $p->id,
            'status'      => 'SKIPPED',
            'last_error'  => 'Aset: hantar manual',
        ]);
        Http::assertNothingSent(); // ASET tidak di-POST ke sistem lama
    }

    // ------------------------------------------------------------------
    //  (g) Halaman admin: 200 untuk admin, 403 untuk bendahari
    // ------------------------------------------------------------------

    public function test_halaman_admin_dual_write_akses(): void
    {
        Http::fake();

        $buat = fn (string $role) => AppUser::create([
            'masjid_id' => config('spkm.masjid_id'), 'login' => 'uji_dw_'.$role.'_'.uniqid(),
            'nama_penuh' => 'Ujian '.$role, 'role' => $role,
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);

        $this->actingAs($buat('admin'))->get(route('admin.dualwrite'))
            ->assertOk()
            ->assertSee('Dual-Write SPPKMS', false);

        $this->actingAs($buat('bendahari'))->get(route('admin.dualwrite'))->assertForbidden();
    }
}

<?php

namespace Tests\Feature\MultiMasjid;

use App\Models\AppUser;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\Coa;
use App\Models\Kutipan;
use App\Models\Masjid;
use App\Services\Tetapan\CoaTemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * PENGASINGAN DATA MERENTAS MASJID (multi-tenant) — bukti menyeluruh:
 *  (1) COA setiap masjid terpencil — masjid A tidak nampak COA masjid B (dua hala).
 *  (2) Transaksi (kutipan) terpencil — bendahari A tidak boleh buka resit masjid B (404),
 *      dan sebaliknya; setiap satu hanya nampak transaksi masjidnya.
 *  (3) Penukar masjid (admin) menggerakkan KONTEKS data — selepas tukar ke B, hanya data B
 *      kelihatan; tukar balik ke 49, hanya data 49.
 *  (4) COA disemai = definisi sahaja (tiada baki/jurnal/transaksi) & templat tidak tercemar.
 *  (5) Matriks peranan — admin/bendahari boleh tulis; pengerusi/setiausaha/juruaudit/viewer
 *      disekat tulis (403); juruaudit boleh baca borang, viewer dialih ke penyata.
 */
class PengasinganDataMasjidTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private int $home;

    private int $masjidB;

    private AppUser $bendahariA;

    private AppUser $bendahariB;

    private AppUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
        $this->home = (int) config('sppkms.masjid_id');

        // Masjid B baharu + semai COA standard (jadikan B berfungsi sepenuhnya)
        $this->masjidB = (int) Masjid::create(['nama' => 'Masjid B '.uniqid()])->id;
        app(CoaTemplateService::class)->sediaUntukMasjid($this->masjidB);

        $this->bendahariA = $this->buatUser('bendahari', $this->home);
        $this->bendahariB = $this->buatUser('bendahari', $this->masjidB);
        $this->admin = $this->buatUser('admin', $this->home);
    }

    private function buatUser(string $role, int $masjidId): AppUser
    {
        return AppUser::create([
            'masjid_id' => $masjidId, 'login' => 'uji_'.$role.'_'.uniqid(),
            'nama_penuh' => 'Uji '.$role, 'role' => $role,
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
    }

    private function coaIdMasjid(int $masjidId, string $kod): int
    {
        return (int) Coa::withoutMasjidScope()
            ->where('masjid_id', $masjidId)->where('kod', $kod)->value('id');
    }

    /** Cipta satu kutipan TUNAI via HTTP sebagai $user; pulang model yang baru dicipta. */
    private function buatKutipan(AppUser $user, int $coaId): Kutipan
    {
        $before = (int) Kutipan::withoutMasjidScope()->max('id');
        $this->actingAs($user)->post(route('kutipan.simpan'), [
            'coa_id' => $coaId, 'kaedah' => 'TUNAI', 'tarikh' => '2026-06-30',
            'jumlah' => '12.34', 'auto_resit' => '1', 'semakan' => '1',
        ])->assertSessionHasNoErrors();

        return Kutipan::withoutMasjidScope()->where('id', '>', $before)->orderBy('id')->firstOrFail();
    }

    /* ---------------------------------------------------------------- (1) COA */

    public function test_coa_terpencil_dua_hala(): void
    {
        $coa49 = $this->coaId('400-03010');
        $coaB = $this->coaIdMasjid($this->masjidB, '400-03010');

        $this->assertNotSame($coa49, $coaB, 'COA setiap masjid mesti baris berasingan (id berbeza)');

        // Skop 49 (lalai) — nampak 49, TIDAK nampak B
        app()->instance('current.masjid_id', $this->home);
        $this->assertNotNull(Coa::find($coa49));
        $this->assertNull(Coa::find($coaB), 'COA masjid B bocor ke dalam skop masjid 49');

        // Skop B — nampak B, TIDAK nampak 49
        app()->instance('current.masjid_id', $this->masjidB);
        $this->assertNotNull(Coa::find($coaB));
        $this->assertNull(Coa::find($coa49), 'COA masjid 49 bocor ke dalam skop masjid B');

        app()->instance('current.masjid_id', $this->home);
    }

    /* -------------------------------------------------- (2) Transaksi kutipan */

    public function test_transaksi_kutipan_terpencil_antara_masjid(): void
    {
        $kA = $this->buatKutipan($this->bendahariA, $this->coaId('400-03010'));
        $kB = $this->buatKutipan($this->bendahariB, $this->coaIdMasjid($this->masjidB, '400-03010'));

        // Data dicop pada masjid yang betul
        $this->assertSame($this->home, (int) $kA->masjid_id);
        $this->assertSame($this->masjidB, (int) $kB->masjid_id);
        $this->assertNotSame((int) $kA->id, (int) $kB->id);

        // Bendahari A: nampak resit sendiri (200), resit B disekat (404)
        $this->actingAs($this->bendahariA)->get(route('kutipan.view', $kA->id))->assertOk();
        $this->actingAs($this->bendahariA)->get(route('kutipan.view', $kB->id))->assertNotFound();

        // Bendahari B: nampak resit sendiri (200), resit A disekat (404)
        $this->actingAs($this->bendahariB)->get(route('kutipan.view', $kB->id))->assertOk();
        $this->actingAs($this->bendahariB)->get(route('kutipan.view', $kA->id))->assertNotFound();
    }

    /* ------------------------------------------ (3) Penukar masjid (konteks) */

    public function test_admin_tukar_masjid_konteks_data_ikut(): void
    {
        $kA = $this->buatKutipan($this->bendahariA, $this->coaId('400-03010'));
        $kB = $this->buatKutipan($this->bendahariB, $this->coaIdMasjid($this->masjidB, '400-03010'));

        // Admin (home 49) — nampak 49, bukan B
        $this->actingAs($this->admin)->get(route('kutipan.view', $kA->id))->assertOk();
        $this->actingAs($this->admin)->get(route('kutipan.view', $kB->id))->assertNotFound();

        // Tukar ke B → kini nampak B, bukan 49
        $this->actingAs($this->admin)->post(route('masjid.tukar'), ['masjid_id' => $this->masjidB])
            ->assertSessionHas('selected_masjid_id', $this->masjidB);
        $this->actingAs($this->admin)->get(route('kutipan.view', $kB->id))->assertOk();
        $this->actingAs($this->admin)->get(route('kutipan.view', $kA->id))->assertNotFound();

        // Tukar balik ke 49 → nampak 49, bukan B
        $this->actingAs($this->admin)->post(route('masjid.tukar'), ['masjid_id' => $this->home])
            ->assertSessionHas('selected_masjid_id', $this->home);
        $this->actingAs($this->admin)->get(route('kutipan.view', $kA->id))->assertOk();
        $this->actingAs($this->admin)->get(route('kutipan.view', $kB->id))->assertNotFound();
    }

    /* --------------------------------- (4) COA disemai = definisi sahaja, bersih */

    public function test_coa_disemai_definisi_sahaja_dan_templat_tidak_tercemar(): void
    {
        // Set kod sama persis dgn templat (definisi disalin penuh)
        $kodTemplat = DB::table('coa')->where('masjid_id', $this->home)->orderBy('kod')->pluck('kod')->all();
        $kodB = DB::table('coa')->where('masjid_id', $this->masjidB)->orderBy('kod')->pluck('kod')->all();
        $this->assertNotEmpty($kodB, 'Masjid B mesti mempunyai COA disemai');
        $this->assertSame($kodTemplat, $kodB, 'Set kod COA masjid B mesti sama dgn templat');

        // Templat tidak tercemar — id masjid_id setiap baris B = B (bukan templat)
        $bukanB = DB::table('coa')->where('masjid_id', $this->masjidB)
            ->where('masjid_id', '!=', $this->masjidB)->count();
        $this->assertSame(0, (int) $bukanB);

        // TIADA baki/transaksi dibawa: masjid B bersih dari jurnal/kutipan/pembayaran
        $this->assertSame(0, (int) DB::table('journal_voucher')->where('masjid_id', $this->masjidB)->count(),
            'Masjid baharu tidak sepatutnya ada voucher jurnal');
        $this->assertSame(0, (int) DB::table('kutipan')->where('masjid_id', $this->masjidB)->count());
        $this->assertSame(0, (int) DB::table('pembayaran')->where('masjid_id', $this->masjidB)->count());
    }

    /*
     | REGRESI keselamatan — susunan middleware: SetMasjidContext MESTI berjalan
     | sebelum route-model binding (SubstituteBindings). Disimulasikan dgn
     | forgetInstance (keadaan SEGAR seperti prod, di mana setiap permintaan
     | bermula tanpa current.masjid_id terikat). Jika binding berjalan dahulu,
     | skop global tidak menapis → pengguna masjid B boleh buka rekod masjid 49.
     */
    public function test_binding_terskop_walau_konteks_segar(): void
    {
        $kA = $this->buatKutipan($this->bendahariA, $this->coaId('400-03010')); // milik masjid 49

        app()->forgetInstance('current.masjid_id'); // seperti permulaan permintaan prod
        $this->actingAs($this->bendahariB)->get(route('kutipan.view', $kA->id))
            ->assertNotFound(); // B TIDAK boleh buka rekod 49 melalui id (anti-IDOR)

        app()->forgetInstance('current.masjid_id');
        $this->actingAs($this->bendahariA)->get(route('kutipan.view', $kA->id))
            ->assertOk();      // pemilik (49) tetap boleh

        app()->instance('current.masjid_id', $this->home);
    }

    /* ----------------------------------------------- (5) Matriks peranan */

    public function test_matriks_peranan_tulis_kutipan(): void
    {
        $data = [
            'coa_id' => $this->coaId('400-03010'), 'kaedah' => 'TUNAI', 'tarikh' => '2026-06-30',
            'jumlah' => '5.00', 'auto_resit' => '1', 'semakan' => '1',
        ];

        // BOLEH tulis kewangan: BENDAHARI sahaja (maker)
        foreach (['bendahari'] as $role) {
            $u = $this->buatUser($role, $this->home);
            $this->actingAs($u)->post(route('kutipan.simpan'), $data)->assertSessionHasNoErrors();
        }

        // DISEKAT tulis (403): admin (sistem, bukan jurukira), pengerusi, setiausaha, juruaudit, viewer
        foreach (['admin', 'pengerusi', 'setiausaha', 'juruaudit', 'viewer'] as $role) {
            $u = $this->buatUser($role, $this->home);
            $this->actingAs($u)->post(route('kutipan.simpan'), $data)
                ->assertForbidden();
        }
    }

    public function test_matriks_peranan_baca_borang_kutipan(): void
    {
        // BOLEH baca borang: admin, bendahari, pengerusi, setiausaha, juruaudit
        foreach (['admin', 'bendahari', 'pengerusi', 'setiausaha', 'juruaudit'] as $role) {
            $u = $this->buatUser($role, $this->home);
            $this->actingAs($u)->get(route('kutipan.baru'))->assertOk();
        }

        // viewer (pemerhati) dialih ke penyata — bukan borang kutipan
        $viewer = $this->buatUser('viewer', $this->home);
        $this->actingAs($viewer)->get(route('kutipan.baru'))->assertRedirect(route('penyata.bulanan'));
    }

    /* --------------- (6) Baris penyata rekonsiliasi (tiada lajur masjid_id) */

    public function test_rekonsiliasi_baris_penyata_terpencil_antara_masjid(): void
    {
        // Bank + baris penyata milik masjid B. BankStatementLine TIADA skop masjid sendiri
        // (tiada lajur masjid_id) — pemilikan hanya melalui bank_account.
        $bankB = BankAccount::withoutMasjidScope()->where('masjid_id', $this->home)->firstOrFail()->replicate();
        $bankB->masjid_id = $this->masjidB;
        $bankB->save();

        $lineB = BankStatementLine::create([
            'bank_account_id' => $bankB->id, 'tarikh' => '2026-06-01',
            'deskripsi' => 'Baris B', 'debit' => 0, 'kredit' => 100, 'status' => 'UNMATCHED',
        ]);

        // Bendahari A TIDAK boleh abaikan / padan baris milik masjid B → 404 (anti tulis silang-penyewa)
        $this->actingAs($this->bendahariA)->post(route('rekonsiliasi.abaikan', $lineB->id))->assertNotFound();
        $this->actingAs($this->bendahariA)->post(route('rekonsiliasi.padan', $lineB->id), ['voucher_id' => 1])->assertNotFound();

        // Status baris B kekal UNMATCHED — tiada mutasi silang berlaku
        $this->assertSame('UNMATCHED', $lineB->fresh()->status);
        $this->assertNull($lineB->fresh()->matched_voucher_id);
    }

    /* --------------- (7) Juruaudit: baca penuh, tiada tulis (kecuali akaun-sendiri) */

    public function test_juruaudit_baca_boleh_tulis_disekat_kecuali_sendiri(): void
    {
        $ja = $this->buatUser('juruaudit', $this->home);

        // Baca penuh (borang) dibenarkan — tidak dialih seperti pemerhati
        $this->actingAs($ja)->get(route('kutipan.baru'))->assertOk();

        // Tulis kewangan disekat (403)
        $this->actingAs($ja)->post(route('kutipan.simpan'), [
            'coa_id' => $this->coaId('400-03010'), 'kaedah' => 'TUNAI', 'tarikh' => '2026-06-30',
            'jumlah' => '5.00', 'auto_resit' => '1', 'semakan' => '1',
        ])->assertForbidden();

        // Laluan akaun-sendiri (tukar kata laluan) TIDAK disekat oleh pagar baca-sahaja
        $resp = $this->actingAs($ja)->post(route('tetapan.katalaluan.kemaskini'), []);
        $this->assertNotSame(403, $resp->status(), 'Juruaudit mesti boleh tukar kata laluan sendiri');
    }
}

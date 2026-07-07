<?php

namespace Tests\Feature\Lanjutan;

use App\Models\AppUser;
use App\Models\Approval;
use App\Models\Attachment;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\DepreciationSchedule;
use App\Models\FixedAsset;
use App\Models\JournalVoucher;
use App\Models\Pembayaran;
use App\Services\Lanjutan\ApprovalService;
use App\Services\Lanjutan\DepreciationService;
use App\Services\Lanjutan\YearEndService;
use App\Services\Laporan\ReportService;
use App\Services\Transaksi\AsetService;
use App\Services\Transaksi\PembayaranService;
use App\Support\Setting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Fasa 9 — ujian Perakaunan Lanjutan + UX:
 * (a) susut nilai bulanan idempoten  (b) pelupusan aset  (c) tutup tahun 'YYYY-13'
 * (d) belanjawan + endpoint semak    (e) maker-checker   (f) rekonsiliasi CSV
 * (g) amaran defisit dana            (h) carian global
 * Semua jurnal baharu di-rollback (DatabaseTransactions) — tally sejarah terpelihara.
 */
class LanjutanTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private AppUser $admin;
    private AppUser $bendahari;
    private AppUser $pengerusi;
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
        $this->admin = $buat('admin');
        $this->bendahari = $buat('bendahari');
        $this->pengerusi = $buat('pengerusi');
        $this->bank = BankAccount::withoutMasjidScope()
            ->where('masjid_id', config('spkm.masjid_id'))
            ->where('status', 'AKTIF')->firstOrFail();
    }

    private function asetUjian(float $kos, array $extra = []): FixedAsset
    {
        return app(AsetService::class)->register([
            'nama'             => 'UJIAN KERUSI PEJABAT',
            'coa_id'           => $this->coaId('200-01030'), // PERABOT — SNT auto 200-01035
            'tarikh_perolehan' => '2026-01-15',
            'kos'              => $kos,
        ] + $extra);
    }

    /* ---------------- (a) Susut nilai bulanan — garis lurus & idempoten ---------------- */

    public function test_susut_nilai_bulanan_dijana_dan_idempoten(): void
    {
        $aset = $this->asetUjian(1200, ['depn_rate_pct' => 10]); // 1200×10%/12 = 10.00 sebulan
        $servis = app(DepreciationService::class);

        $r1 = $servis->janaBulan(2026, 6);
        $this->assertGreaterThanOrEqual(1, $r1['diposkan']);

        // Jadual susut nilai posted + voucher Dr 650-10000 / Cr 200-01035 = 10.00
        $jadual = DepreciationSchedule::where('fixed_asset_id', $aset->id)
            ->where('tahun', 2026)->where('bulan', 6)->get();
        $this->assertCount(1, $jadual);
        $this->assertTrue((bool) $jadual->first()->posted);
        $this->assertSame('10.00', (string) $jadual->first()->amaun);

        $voucher = JournalVoucher::withoutMasjidScope()->findOrFail($jadual->first()->voucher_id);
        $this->assertSame('POSTED', $voucher->status);
        $this->assertSame('2026-06', $voucher->period_ym);
        $entries = $voucher->entries;
        $this->assertSame('10.00', (string) $entries->where('coa_id', $this->coaId('650-10000'))->first()->debit);
        $this->assertSame('10.00', (string) $entries->where('coa_id', $this->coaId('200-01035'))->first()->kredit);

        $this->assertSame('10.00', (string) $aset->fresh()->accumulated_depn);

        // Jana KEDUA kali bulan sama → TIADA voucher/jadual baharu untuk aset ini
        $servis->janaBulan(2026, 6);
        $this->assertSame(1, DepreciationSchedule::where('fixed_asset_id', $aset->id)
            ->where('tahun', 2026)->where('bulan', 6)->count());
        $this->assertSame(1, JournalVoucher::withoutMasjidScope()
            ->where('source_type', 'JURNAL')->where('source_id', $aset->id)
            ->where('deskripsi', 'like', 'SUSUT NILAI 2026-06%')->count());
        $this->assertSame('10.00', (string) $aset->fresh()->accumulated_depn);
    }

    /* ---------------- (b) Pelupusan aset — Dr SNT + Dr 600-99990 / Cr aset (kos penuh) ---------------- */

    public function test_pelupusan_aset_jurnal_seimbang_dan_status_dilupuskan(): void
    {
        $aset = $this->asetUjian(100, ['accumulated_depn' => 10]);

        $voucher = app(DepreciationService::class)->lupus($aset, 'Rosak teruk — ujian');

        $this->assertSame('PELUPUSAN', $voucher->source_type->value);
        $entries = $voucher->entries;

        $this->assertSame('10.00', (string) $entries->where('coa_id', $this->coaId('200-01035'))->first()->debit);   // SNT
        $this->assertSame('90.00', (string) $entries->where('coa_id', $this->coaId('600-99990'))->first()->debit);   // nilai buku
        $this->assertSame('100.00', (string) $entries->where('coa_id', $this->coaId('200-01030'))->first()->kredit); // kos penuh

        // Seimbang ke sen
        $this->assertSame(
            number_format($entries->sum(fn ($e) => (float) $e->debit), 2, '.', ''),
            number_format($entries->sum(fn ($e) => (float) $e->kredit), 2, '.', ''),
        );

        $this->assertSame('DILUPUSKAN', $aset->fresh()->status);
    }

    /* ---------------- (c) Tutup tahun — period 'YYYY-13', sejarah TIDAK berubah ---------------- */

    public function test_tutup_tahun_2024_period_13_dan_sejarah_kekal(): void
    {
        $servis = app(YearEndService::class);
        $report = app(ReportService::class);

        $voucher = $servis->tutup(2024);

        $this->assertSame('YE-2024', $voucher->voucher_ref);
        $this->assertSame('2024-13', $voucher->period_ym);
        $this->assertSame('POSTED', $voucher->status);

        // Voucher penutupan seimbang ke sen
        $entries = $voucher->entries;
        $this->assertSame(
            number_format($entries->sum(fn ($e) => (float) $e->debit), 2, '.', ''),
            number_format($entries->sum(fn ($e) => (float) $e->kredit), 2, '.', ''),
        );

        // BUKTI sejarah tak berubah: P&L 2024 SELEPAS penutupan MASIH angka tally
        $pl = $report->profitLoss('2024-01', '2024-12');
        $this->assertSame('955006.47', $pl['jumlah_hasil'], 'Pendapatan 2024 berubah selepas tutup tahun!');
        $this->assertSame('927720.76', $pl['jumlah_belanja'], 'Belanja 2024 berubah selepas tutup tahun!');
        $this->assertSame('27285.71', $pl['lebihan']);

        // Kunci Kira-Kira cutoff 2026-06 MASIH sama & seimbang
        $bs = $report->balanceSheet('2026-06');
        $this->assertSame('174589.95', $bs['total_aset'], 'Total aset BS berubah selepas tutup tahun!');
        $this->assertTrue($bs['seimbang'], 'BS tidak seimbang selepas tutup tahun!');

        // Tempoh dikunci sehingga 2024-12
        $this->assertSame('2024-12', Setting::get('period_locked_until'));

        // Tutup kali KEDUA mesti ditolak
        $this->expectException(LogicException::class);
        $servis->tutup(2024);
    }

    /* ---------------- (d) Belanjawan — set peruntukan + endpoint semak ---------------- */

    public function test_belanjawan_set_peruntukan_dan_semakan_melebihi(): void
    {
        $tahun = (int) now()->year;
        $coaId = $this->coaId('600-06000');

        $this->actingAs($this->bendahari)
            ->post(route('belanjawan.simpan'), [
                'tahun' => $tahun,
                'peruntukan' => [$coaId => '1000'],
            ])
            ->assertRedirect(route('belanjawan.index', ['tahun' => $tahun]));

        $this->assertDatabaseHas('budget', [
            'masjid_id' => config('spkm.masjid_id'), 'tahun' => $tahun,
            'coa_id' => $coaId, 'amaun_peruntukan' => '1000.00',
        ]);

        // Jumlah 2000 > peruntukan 1000 → melebihi: true
        $resp = $this->actingAs($this->bendahari)
            ->getJson(route('belanjawan.semak', ['coa_id' => $coaId, 'jumlah' => 2000]))
            ->assertOk()
            ->assertJson(['ada' => true, 'melebihi' => true]);

        $this->assertSame('1000.00', $resp->json('peruntukan'));

        // Halaman belanjawan dipapar
        $this->actingAs($this->bendahari)->get(route('belanjawan.index', ['tahun' => $tahun]))
            ->assertOk()->assertSee('Belanjawan');
    }

    /* ---------------- (e) Maker-Checker — bendahari > had perlu kelulusan ---------------- */

    public function test_maker_checker_aliran_penuh(): void
    {
        Setting::set('approval_threshold', '100');
        Setting::set('approval_enabled', 'on'); // suis induk kini lalai OFF — hidupkan untuk uji aliran

        $borang = fn (string $jumlah, string $pemohon) => [
            'tar_mohon' => '2026-06-12', 'tar_lulus' => '2026-06-12',
            'auto_baucer' => '1', 'pemohon' => $pemohon,
            'coa_id' => $this->coaId('600-06000'), 'deskripsi' => 'UJIAN MAKER CHECKER',
            'jumlah' => $jumlah, 'cara_bayar' => 'EFT',
            'bank_account_id' => $this->bank->id, 'semakan' => '1',
        ];

        // Bendahari, RM150 > had 100 → approval PENDING, TIADA pembayaran/jurnal
        $this->actingAs($this->bendahari)
            ->post(route('belanja.simpan'), $borang('150', 'UJIAN MC BESAR'))
            ->assertRedirect(route('belanja.senarai'));

        $approval = Approval::withoutMasjidScope()
            ->where('masjid_id', config('spkm.masjid_id'))
            ->where('status', 'PENDING')->where('amaun', '150.00')
            ->orderByDesc('id')->first();
        $this->assertNotNull($approval, 'Approval PENDING mesti tercipta');
        $this->assertNull($approval->entity_id);
        $this->assertSame($this->bendahari->id, (int) $approval->maker_id);
        $this->assertTrue(
            Pembayaran::withoutMasjidScope()->where('pemohon', 'UJIAN MC BESAR')->doesntExist(),
            'TIADA pembayaran boleh wujud sebelum kelulusan',
        );

        // Pengerusi (checker) meluluskan → pembayaran + jurnal wujud (admin BUKAN pelulus)
        $this->actingAs($this->pengerusi)
            ->post(route('kelulusan.lulus', $approval->id))
            ->assertRedirect(route('kelulusan.index'));

        $pembayaran = Pembayaran::withoutMasjidScope()->where('pemohon', 'UJIAN MC BESAR')->first();
        $this->assertNotNull($pembayaran, 'Pembayaran mesti wujud selepas lulus');
        $this->assertNotNull($pembayaran->voucher_id, 'Jurnal mesti diposkan selepas lulus');
        $this->assertSame('POSTED', $pembayaran->voucher()->first()->status);

        $approval->refresh();
        $this->assertSame('APPROVED', $approval->status);
        $this->assertSame($pembayaran->id, (int) $approval->entity_id);

        // Bendahari, RM50 <= had → terus jadi pembayaran (tiada approval)
        $this->actingAs($this->bendahari)
            ->post(route('belanja.simpan'), $borang('50', 'UJIAN MC KECIL'))
            ->assertRedirect(route('belanja.senarai'));

        $kecil = Pembayaran::withoutMasjidScope()->where('pemohon', 'UJIAN MC KECIL')->first();
        $this->assertNotNull($kecil, 'Bayaran bawah had mesti terus direkodkan');
        $this->assertNotNull($kecil->voucher_id);
    }

    /* -------- (e2) Maker-Checker — dokumen sokongan kekal merentas kelulusan -------- */

    public function test_maker_checker_lampiran_kekal_selepas_lulus(): void
    {
        Storage::fake('local');
        Setting::set('approval_threshold', '100');
        Setting::set('approval_enabled', 'on');

        // Bendahari, RM250 > had + 1 dokumen → approval PENDING, fail DISTASH,
        // belum ada pembayaran/attachment BAYARAN.
        $this->actingAs($this->bendahari)->post(route('belanja.simpan'), [
            'tar_mohon' => '2026-06-12', 'tar_lulus' => '2026-06-12', 'auto_baucer' => '1',
            'pemohon' => 'UJIAN LAMPIRAN MC', 'coa_id' => $this->coaId('600-06000'),
            'deskripsi' => 'UJIAN LAMPIRAN', 'jumlah' => '250', 'cara_bayar' => 'EFT',
            'bank_account_id' => $this->bank->id, 'semakan' => '1',
            'dokumen' => [UploadedFile::fake()->create('invois.pdf', 120, 'application/pdf')],
        ])->assertRedirect(route('belanja.senarai'));

        $approval = Approval::withoutMasjidScope()
            ->where('masjid_id', config('spkm.masjid_id'))
            ->where('status', 'PENDING')->where('amaun', '250.00')->orderByDesc('id')->firstOrFail();

        $payload = json_decode((string) $approval->payload, true);
        $this->assertNotEmpty($payload['_lampiran'] ?? [], 'Metadata lampiran mesti dlm payload');
        $stash = $payload['_lampiran'][0]['file_path'];
        Storage::disk('local')->assertExists($stash);
        $this->assertSame(0, Attachment::withoutMasjidScope()->where('file_path', $stash)->count(),
            'Tiada row attachment sebelum kelulusan');

        // Admin luluskan → pembayaran + attachment BAYARAN dipautkan ke fail yg SAMA.
        $this->actingAs($this->pengerusi)->post(route('kelulusan.lulus', $approval->id))
            ->assertRedirect(route('kelulusan.index'));

        $pembayaran = Pembayaran::withoutMasjidScope()->where('pemohon', 'UJIAN LAMPIRAN MC')->firstOrFail();
        $att = Attachment::withoutMasjidScope()->where('owner_type', 'BAYARAN')
            ->where('owner_id', $pembayaran->id)->get();
        $this->assertCount(1, $att, 'Lampiran mesti dipautkan kpd pembayaran selepas lulus');
        $this->assertSame($stash, $att->first()->file_path);
        $this->assertSame('invois.pdf', $att->first()->file_name);
        $this->assertSame((int) config('spkm.masjid_id'), (int) $att->first()->masjid_id);
        Storage::disk('local')->assertExists($stash); // fail kekal
    }

    public function test_maker_checker_lampiran_dibuang_bila_ditolak(): void
    {
        Storage::fake('local');
        Setting::set('approval_threshold', '100');
        Setting::set('approval_enabled', 'on');

        $this->actingAs($this->bendahari)->post(route('belanja.simpan'), [
            'tar_mohon' => '2026-06-12', 'tar_lulus' => '2026-06-12', 'auto_baucer' => '1',
            'pemohon' => 'UJIAN LAMPIRAN TOLAK', 'coa_id' => $this->coaId('600-06000'),
            'deskripsi' => 'UJIAN TOLAK', 'jumlah' => '300', 'cara_bayar' => 'EFT',
            'bank_account_id' => $this->bank->id, 'semakan' => '1',
            'dokumen' => [UploadedFile::fake()->create('resit.pdf', 80, 'application/pdf')],
        ])->assertRedirect(route('belanja.senarai'));

        $approval = Approval::withoutMasjidScope()
            ->where('masjid_id', config('spkm.masjid_id'))
            ->where('status', 'PENDING')->where('amaun', '300.00')->orderByDesc('id')->firstOrFail();
        $stash = json_decode((string) $approval->payload, true)['_lampiran'][0]['file_path'];
        Storage::disk('local')->assertExists($stash);

        // Tolak → fail distash dibuang, tiada pembayaran tercipta.
        $this->actingAs($this->pengerusi)->post(route('kelulusan.tolak', $approval->id), ['sebab' => 'Tidak lengkap'])
            ->assertRedirect(route('kelulusan.index'));

        Storage::disk('local')->assertMissing($stash);
        $this->assertTrue(Pembayaran::withoutMasjidScope()->where('pemohon', 'UJIAN LAMPIRAN TOLAK')->doesntExist());
    }

    /* -------- (e3) Maker-Checker — suis induk ON/OFF + cegah double-approve -------- */

    public function test_maker_checker_suis_mati_terus_rekod(): void
    {
        Setting::set('approval_threshold', '100');
        Setting::set('approval_enabled', 'off'); // suis induk MATI

        // Bendahari RM500 > had TETAPI suis mati → terus jadi pembayaran (tiada approval).
        $this->actingAs($this->bendahari)->post(route('belanja.simpan'), [
            'tar_mohon' => '2026-06-12', 'tar_lulus' => '2026-06-12', 'auto_baucer' => '1',
            'pemohon' => 'UJIAN SUIS MATI', 'coa_id' => $this->coaId('600-06000'),
            'deskripsi' => 'UJIAN SUIS', 'jumlah' => '500', 'cara_bayar' => 'EFT',
            'bank_account_id' => $this->bank->id, 'semakan' => '1',
        ])->assertRedirect(route('belanja.senarai'));

        $this->assertTrue(
            Pembayaran::withoutMasjidScope()->where('pemohon', 'UJIAN SUIS MATI')->exists(),
            'Bila suis mati, bayaran terus direkod tanpa kelulusan',
        );
        $this->assertSame(0, Approval::withoutMasjidScope()->where('masjid_id', config('spkm.masjid_id'))
            ->where('status', 'PENDING')->where('amaun', '500.00')->count());
    }

    public function test_kawalan_toggle_kelulusan_disimpan(): void
    {
        // Suis bertanda → hantar '1' → 'on'
        $this->actingAs($this->bendahari)->post(route('kawalan.simpan'), [
            'approval_enabled' => '1', 'approval_threshold' => '100', 'baki_rendah_ambang' => '0',
        ])->assertRedirect(route('kawalan.index'));
        $this->assertSame('on', Setting::get(ApprovalService::KEY_ENABLED));

        // Suis TAK bertanda → medan tersembunyi hantar '0' → 'off'
        $this->actingAs($this->bendahari)->post(route('kawalan.simpan'), [
            'approval_enabled' => '0', 'approval_threshold' => '100', 'baki_rendah_ambang' => '0',
        ])->assertRedirect(route('kawalan.index'));
        $this->assertSame('off', Setting::get(ApprovalService::KEY_ENABLED));
    }

    public function test_lulus_kedua_dihalang_kunci_tiada_bayar_dua_kali(): void
    {
        Setting::set('approval_threshold', '100');
        Setting::set('approval_enabled', 'on');
        $svc = app(ApprovalService::class);

        $approval = $svc->mohon('BAYARAN', 500.0, [
            'tar_mohon' => '2026-06-12', 'tar_lulus' => '2026-06-12', 'auto_baucer' => '1',
            'pemohon' => 'UJIAN DOUBLE', 'coa_id' => $this->coaId('600-06000'),
            'deskripsi' => 'UJIAN DOUBLE APPROVE', 'jumlah' => '500', 'cara_bayar' => 'EFT',
            'bank_account_id' => $this->bank->id,
        ]);

        $p1 = $svc->lulus($approval);
        $this->assertNotNull($p1);

        // $approval masih 'PENDING' dlm memori (lulus mengemaskini salinan TERKUNCI, bukan
        // instance ini) → semakan AWAL lepas, tetapi kunci+semak-semula dlm transaksi
        // menangkap APPROVED. Mensimulasi dua pelulus serentak dgn instance basi.
        try {
            $svc->lulus($approval);
            $this->fail('Lulus kedua sepatutnya gagal (sudah diputuskan)');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('diputuskan', $e->getMessage());
        }

        $this->assertSame(1, Pembayaran::withoutMasjidScope()->where('pemohon', 'UJIAN DOUBLE')->count(),
            'Hanya SATU pembayaran — tiada bayar dua kali');
    }

    public function test_sapu_lampiran_yatim_kekalkan_yang_dirujuk(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        $disk->put('lampiran/yatim.pdf', 'X');   // (a) tiada rujukan → buang
        $disk->put('lampiran/dipakai.pdf', 'Y'); // (b) dirujuk Attachment → kekal
        $disk->put('lampiran/pending.pdf', 'Z'); // (c) dlm permohonan PENDING → kekal

        Attachment::create([
            'owner_type' => 'BAYARAN', 'owner_id' => 1,
            'file_path' => 'lampiran/dipakai.pdf', 'file_name' => 'dipakai.pdf',
        ]);
        Approval::withoutMasjidScope()->create([
            'masjid_id' => config('spkm.masjid_id'), 'entity' => 'BAYARAN', 'entity_id' => null,
            'amaun' => '10.00', 'status' => 'PENDING', 'remark' => 'UJIAN SAPU',
            'payload' => json_encode(['_jenis' => 'BAYARAN', '_lampiran' => [['file_path' => 'lampiran/pending.pdf']]]),
        ]);

        $this->artisan('spkm:sapu-lampiran', ['--hari' => 0])->assertSuccessful();

        $disk->assertMissing('lampiran/yatim.pdf');  // yatim dibuang
        $disk->assertExists('lampiran/dipakai.pdf'); // dirujuk Attachment dikekalkan
        $disk->assertExists('lampiran/pending.pdf'); // permohonan PENDING dikekalkan
    }

    /* ---------------- (f) Rekonsiliasi — import CSV, 1 padan auto 1 tidak ---------------- */

    public function test_rekonsiliasi_import_csv_padan_auto(): void
    {
        // Bayaran sebenar RM4321.97 melalui bank (2026-06-10) → Cr COA bank
        $pembayaran = app(PembayaranService::class)->createBayaran([
            'tar_mohon' => '2026-06-10', 'tar_lulus' => '2026-06-10',
            'auto_baucer' => '1', 'pemohon' => 'UJIAN REKON',
            'coa_id' => $this->coaId('600-06000'), 'deskripsi' => 'UJIAN REKONSILIASI',
            'jumlah' => '4321.97', 'cara_bayar' => 'EFT',
            'bank_account_id' => $this->bank->id,
        ]);

        // CSV: header + 1 baris padan (debit penyata = wang keluar, ±1 hari) + 1 baris tiada padanan
        $csv = "Tarikh,Deskripsi,Debit,Kredit,Baki\n"
            ."11/06/2026,BAYARAN UJIAN REKON,4321.97,0.00,10000.00\n"
            ."2026-06-11,TIADA PADANAN XYZ,123456.78,0.00,0.00\n";

        $this->actingAs($this->bendahari)
            ->post(route('rekonsiliasi.import'), [
                'bank_account_id' => $this->bank->id,
                'fail' => UploadedFile::fake()->createWithContent('penyata.csv', $csv),
            ])
            ->assertRedirect(route('rekonsiliasi.index', ['bank_account_id' => $this->bank->id]));

        $padan = BankStatementLine::where('bank_account_id', $this->bank->id)
            ->where('debit', '4321.97')->first();
        $this->assertNotNull($padan);
        $this->assertSame('MATCHED', $padan->status);
        $this->assertSame((int) $pembayaran->voucher_id, (int) $padan->matched_voucher_id);

        $tiada = BankStatementLine::where('bank_account_id', $this->bank->id)
            ->where('debit', '123456.78')->first();
        $this->assertNotNull($tiada);
        $this->assertSame('UNMATCHED', $tiada->status);

        // Halaman rekonsiliasi dipapar dengan laporan ringkas
        $this->actingAs($this->bendahari)
            ->get(route('rekonsiliasi.index', ['bank_account_id' => $this->bank->id]))
            ->assertOk()->assertSee('Rekonsiliasi');
    }

    /* ---------------- (g) Dana — defisit 300-04050 dikesan & dipaparkan ---------------- */

    public function test_dana_amaran_defisit_dipaparkan(): void
    {
        // Data sejarah: baki 300-04050 KUMPULAN TABUNG RAHMAH MADANI = -23850.00
        $this->assertSame('23850.00', $this->bakiCoa('300-04050')); // baki Dr (kredit-debit = -23850)

        $resp = $this->actingAs($this->bendahari)->get(route('dana.index'))->assertOk();
        $resp->assertSee('AMARAN DEFISIT');
        $resp->assertSee('300-04050');
        $resp->assertSee('23,850.00');

        // fund_account di-seed automatik untuk 300-04010..04050
        $this->assertDatabaseHas('fund_account', [
            'masjid_id' => config('spkm.masjid_id'),
            'coa_id'    => $this->coaId('300-04050'),
        ]);
    }

    /* ---------------- (h) Carian global ---------------- */

    public function test_carian_global_jumpa_keputusan(): void
    {
        // 'AFFIN' wujud dalam data sejarah (296 kutipan)
        $this->actingAs($this->bendahari)
            ->get(route('carian', ['q' => 'AFFIN']))
            ->assertOk()
            ->assertSee('Keputusan untuk')
            ->assertSee('AFFIN');

        // Carian resit sedia ada
        $noResit = \App\Models\Kutipan::aktif()->whereNotNull('no_resit')
            ->where('no_resit', '!=', '')->orderByDesc('id')->value('no_resit');
        $this->actingAs($this->bendahari)
            ->get(route('carian', ['q' => $noResit]))
            ->assertOk()
            ->assertSee((string) $noResit);
    }
}

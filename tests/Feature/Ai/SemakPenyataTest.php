<?php

namespace Tests\Feature\Ai;

use App\Jobs\ProsesPenyataAi;
use App\Models\AppUser;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\Coa;
use App\Models\Kutipan;
use App\Models\Masjid;
use App\Models\Pembayaran;
use App\Models\PenyataSemakan;
use App\Services\Ai\KuotaPenyataService;
use App\Services\Ai\SemakPenyataService;
use App\Services\Security\SecretVaultService;
use App\Services\Tetapan\CoaTemplateService;
use App\Support\Setting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Ciri "Semak Penyata (AI)" hujung-ke-hujung: muat naik → job AI (Http palsu) →
 * baris + cadangan COA → Rekod (kutipan/belanja sebenar) → isolasi + dedup.
 * AI TIDAK pernah pos; hanya rekodBaris (semakan bendahari) yang mencatat.
 */
class SemakPenyataTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private int $masjid;
    private int $bankId;
    private AppUser $bendahari;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
        $this->masjid = (int) config('spkm.masjid_id');
        $this->bankId = (int) BankAccount::withoutMasjidScope()
            ->where('masjid_id', $this->masjid)->value('id');

        $this->bendahari = AppUser::create([
            'masjid_id' => $this->masjid, 'login' => 'sp_b_'.uniqid(),
            'nama_penuh' => 'Bendahari Uji', 'role' => 'bendahari',
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);

        // Konfigurasi AI PUSAT + toggle global
        $vault = app(SecretVaultService::class);
        Setting::set('semak_penyata_enabled', 'on', KuotaPenyataService::MASJID_GLOBAL);
        Setting::set('sp_ai_key_ref', $vault->put('sk-uji-pusat'), KuotaPenyataService::MASJID_GLOBAL);
        Setting::set('sp_ai_model', 'gpt-4o', KuotaPenyataService::MASJID_GLOBAL);

        Storage::fake('local');

        // Poppler tidak dijalankan ke atas fail PALSU dalam ujian — paksa laluan
        // panggilan-tunggal (fallback) supaya mime application/pdf tidak cuba render.
        $this->app->instance(\App\Services\Ai\PdfRenderService::class, new class extends \App\Services\Ai\PdfRenderService {
            public function tersedia(): bool { return false; }
        });
    }

    /** Respons AI palsu: 1 baris masuk (kredit) + 1 baris keluar (debit). */
    private function fakeAi(): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode(['lines' => [
                    ['tarikh' => '2026-06-10', 'deskripsi' => 'INFAQ ONLINE', 'debit' => 0, 'kredit' => 100.00,
                     'cadangan_jenis' => 'KUTIPAN', 'cadangan_coa' => '400-03010', 'confidence' => 90],
                    ['tarikh' => '2026-06-12', 'deskripsi' => 'BAYARAN ELEKTRIK', 'debit' => 50.00, 'kredit' => 0,
                     'cadangan_jenis' => 'BAYARAN', 'cadangan_coa' => '600-06000', 'confidence' => 85],
                ]])],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['total_tokens' => 5000],
        ])]);
    }

    private function muatFail(string $mime = 'application/pdf'): PenyataSemakan
    {
        Queue::fake(); // tangkap dispatch — job dijalankan manual
        $fail = UploadedFile::fake()->create('penyata.pdf', 120, $mime);

        return app(SemakPenyataService::class)->muatNaik($this->bankId, $fail);
    }

    private function jalankanJob(int $batchId): void
    {
        app()->call([new ProsesPenyataAi($batchId), 'handle']);
    }

    /** Pipeline PDF imbasan: pecah kepada 2 muka → OCR satu-satu → GABUNG semua baris + token. */
    public function test_pipeline_pdf_pecah_muka_gabung_semua(): void
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sp_pipe_'.uniqid();
        @mkdir($dir);
        $img1 = $dir.DIRECTORY_SEPARATOR.'muka-1.jpg';
        $img2 = $dir.DIRECTORY_SEPARATOR.'muka-2.jpg';
        file_put_contents($img1, 'JPG1');
        file_put_contents($img2, 'JPG2');

        // Renderer PALSU: tersedia + pulang 2 imej muka (abaikan fail sebenar).
        // ekstrakTeks() → [] memaksa laluan IMBASAN (bukan digital) tanpa spawn pdftotext.
        $this->app->instance(\App\Services\Ai\PdfRenderService::class, new class($img1, $img2) extends \App\Services\Ai\PdfRenderService {
            public function __construct(private string $a, private string $b) {}
            public function tersedia(): bool { return true; }
            public function ekstrakTeks(string $absPdfPath): array { return []; }
            public function renderKeImej(string $absPdfPath, string $destDir, ?int $dpi = null): array { return [$this->a, $this->b]; }
        });

        // AI pulang baris BERBEZA setiap muka (tokens 1000 + 1500).
        $muka = fn (string $tarikh, string $desk, float $kredit, int $tok) => ['choices' => [[
            'message' => ['content' => json_encode(['lines' => [[
                'tarikh' => $tarikh, 'deskripsi' => $desk, 'debit' => 0, 'kredit' => $kredit,
                'cadangan_jenis' => 'KUTIPAN', 'cadangan_coa' => '400-03010', 'confidence' => 90,
            ]]])], 'finish_reason' => 'stop',
        ]], 'usage' => ['total_tokens' => $tok]];
        Http::fakeSequence()
            ->push($muka('2024-02-01', 'INFAQ MUKA1', 10.00, 1000))
            ->push($muka('2024-02-02', 'INFAQ MUKA2', 20.00, 1500));

        $batch = $this->muatFail('application/pdf');
        $this->jalankanJob($batch->id);

        $batch->refresh();
        $this->assertSame('SEDIA', $batch->status);
        $this->assertSame('SCAN', $batch->kaedah);
        $this->assertSame(2, (int) $batch->muka_jumlah);
        $this->assertSame(2, (int) $batch->bil_baris);       // 2 muka digabung
        $this->assertSame(2500, (int) $batch->tokens_used);  // jumlah token 2 panggilan

        @unlink($img1);
        @unlink($img2);
        @rmdir($dir);
    }

    /** PDF DIGITAL: teks terbenam dibaca terus (tanpa OCR imej) → hantar blok TEKS. */
    public function test_pipeline_pdf_digital_baca_teks(): void
    {
        // Renderer PALSU: tersedia + ekstrakTeks pulang 2 muka teks bermakna.
        // renderKeImej() SENGAJA melempar — membuktikan laluan digital tak render imej.
        $this->app->instance(\App\Services\Ai\PdfRenderService::class, new class extends \App\Services\Ai\PdfRenderService {
            public function tersedia(): bool { return true; }
            public function ekstrakTeks(string $absPdfPath): array
            {
                return [
                    "01/02/24  INFAQ ONLINE ZULKEFLI            100.00        1,100.00",
                    "02/02/24  BAYARAN ELEKTRIK TNB    50.00                 1,050.00",
                ];
            }
            public function renderKeImej(string $absPdfPath, string $destDir, ?int $dpi = null): array
            {
                throw new \RuntimeException('renderKeImej TIDAK sepatutnya dipanggil untuk PDF digital');
            }
        });

        $this->fakeAi(); // 1 baris masuk + 1 baris keluar
        $batch = $this->muatFail('application/pdf');
        $this->jalankanJob($batch->id);

        $batch->refresh();
        $this->assertSame('SEDIA', $batch->status);
        $this->assertSame('DIGITAL', $batch->kaedah);
        $this->assertSame(2, (int) $batch->muka_jumlah);
        $this->assertSame(2, (int) $batch->bil_baris);

        // Kandungan dihantar sebagai blok TEKS (bukan file/image_url).
        Http::assertSent(function ($request) {
            foreach ($request->data()['messages'][0]['content'] ?? [] as $blok) {
                if (($blok['type'] ?? '') === 'text' && str_contains($blok['text'] ?? '', 'MUKA')) {
                    return true;
                }
            }

            return false;
        });
    }

    /** Sediakan batch SEDIA + baris UNMATCHED (sisi & jumlah tersuai) untuk ujian lump-sum. */
    private function batchDenganBaris(array $baris): array
    {
        $batch = PenyataSemakan::create([
            'bank_account_id' => $this->bankId, 'file_path' => 'x', 'original_name' => 'x.pdf',
            'mime' => 'application/pdf', 'file_hash' => hash('sha256', uniqid()), 'status' => 'SEDIA',
        ]);
        $ids = [];
        foreach ($baris as $b) {
            $ids[] = BankStatementLine::create([
                'bank_account_id' => $this->bankId, 'tarikh' => $b['tarikh'] ?? '2024-02-01',
                'deskripsi' => $b['desk'] ?? 'QR INFAQ', 'debit' => $b['debit'] ?? 0, 'kredit' => $b['kredit'] ?? 0,
                'status' => 'UNMATCHED', 'batch_id' => $batch->id, 'cadangan_jenis' => ($b['kredit'] ?? 0) > 0 ? 'KUTIPAN' : 'BAYARAN',
                'cadangan_coa_id' => $b['coa'] ?? null, 'ai_confidence' => 50,
            ])->id;
        }

        return [$batch, $ids];
    }

    public function test_lump_sum_longgok_baris_jadi_satu_rekod(): void
    {
        $coaId = $this->coaId('400-03010');
        [, $ids] = $this->batchDenganBaris([
            ['kredit' => 1.00, 'coa' => $coaId], ['kredit' => 2.00, 'coa' => $coaId], ['kredit' => 10.00, 'coa' => $coaId],
        ]);

        $sebelum = (int) Kutipan::withoutMasjidScope()->max('id');
        $r = app(SemakPenyataService::class)->rekodLumpSum($ids, [
            'coa_id' => $coaId, 'tarikh' => '2024-02-01', 'deskripsi' => 'Infaq/Sedekah QR',
        ]);

        $this->assertSame('KUTIPAN', $r['jenis']);
        $this->assertSame(3, $r['bil']);
        $this->assertSame('13.00', $r['jumlah']);

        // SATU kutipan sahaja dicipta untuk 3 baris.
        $this->assertSame(1, Kutipan::withoutMasjidScope()->where('id', '>', $sebelum)->count());

        // Semua 3 baris MATCHED ke voucher SAMA.
        $lines = BankStatementLine::whereIn('id', $ids)->get();
        $this->assertTrue($lines->every(fn ($l) => $l->status === 'MATCHED'));
        $this->assertSame(1, $lines->pluck('matched_voucher_id')->unique()->count());
    }

    public function test_lump_sum_tolak_sisi_bercampur(): void
    {
        $coaId = $this->coaId('400-03010');
        [, $ids] = $this->batchDenganBaris([
            ['kredit' => 10.00, 'coa' => $coaId], ['debit' => 5.00],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(SemakPenyataService::class)->rekodLumpSum($ids, ['coa_id' => $coaId, 'tarikh' => '2024-02-01']);
    }

    public function test_muat_naik_cipta_batch_dan_dispatch(): void
    {
        $batch = $this->muatFail();

        $this->assertSame('UPLOADED', $batch->status);
        $this->assertSame($this->masjid, (int) $batch->masjid_id);
        Queue::assertPushed(ProsesPenyataAi::class);
        Storage::disk('local')->assertExists($batch->file_path);
    }

    public function test_job_ai_ekstrak_baris_dengan_cadangan(): void
    {
        $this->fakeAi();
        $batch = $this->muatFail();
        $this->jalankanJob($batch->id);

        $batch->refresh();
        $this->assertSame('SEDIA', $batch->status);
        $this->assertSame(2, (int) $batch->bil_baris);
        $this->assertSame(5000, (int) $batch->tokens_used);

        $baris = BankStatementLine::where('batch_id', $batch->id)->get();
        $this->assertCount(2, $baris);
        $masuk = $baris->firstWhere('kredit', '100.00');
        $this->assertSame($this->coaId('400-03010'), (int) $masuk->cadangan_coa_id);
        $this->assertSame('KUTIPAN', $masuk->cadangan_jenis);
    }

    public function test_rekod_kutipan_hasilkan_voucher_seimbang(): void
    {
        $this->fakeAi();
        $batch = $this->muatFail();
        $this->jalankanJob($batch->id);

        $line = BankStatementLine::where('batch_id', $batch->id)->where('kredit', '100.00')->firstOrFail();
        $sebelum = (int) Kutipan::withoutMasjidScope()->max('id');

        $r = app(SemakPenyataService::class)->rekodBaris($line, ['coa_id' => $this->coaId('400-03010')]);

        $this->assertSame('KUTIPAN', $r['jenis']);
        $line->refresh();
        $this->assertSame('MATCHED', $line->status);
        $this->assertSame((int) $r['voucher_id'], (int) $line->matched_voucher_id);

        $kutipan = Kutipan::withoutMasjidScope()->where('id', '>', $sebelum)->firstOrFail();
        $this->assertSame('100.00', number_format((float) $kutipan->jumlah, 2, '.', ''));

        // Voucher seimbang: Σdebit = Σkredit
        $jum = \DB::table('journal_entry')->where('voucher_id', $r['voucher_id'])
            ->selectRaw('SUM(debit) d, SUM(kredit) k')->first();
        $this->assertSame(number_format($jum->d, 2, '.', ''), number_format($jum->k, 2, '.', ''));
    }

    public function test_rekod_belanja_hasilkan_pembayaran(): void
    {
        $this->fakeAi();
        $batch = $this->muatFail();
        $this->jalankanJob($batch->id);

        $line = BankStatementLine::where('batch_id', $batch->id)->where('debit', '50.00')->firstOrFail();
        $sebelum = (int) Pembayaran::withoutMasjidScope()->max('id');

        $r = app(SemakPenyataService::class)->rekodBaris($line, ['coa_id' => $this->coaId('600-06000')]);

        $this->assertSame('BAYARAN', $r['jenis']);
        $this->assertSame('MATCHED', $line->fresh()->status);
        $this->assertTrue(Pembayaran::withoutMasjidScope()->where('id', '>', $sebelum)->exists());
    }

    public function test_dedup_fail_sama_ditolak(): void
    {
        Queue::fake();
        $fail = UploadedFile::fake()->create('penyata.pdf', 120, 'application/pdf');
        app(SemakPenyataService::class)->muatNaik($this->bankId, $fail);

        $this->expectException(\InvalidArgumentException::class);
        app(SemakPenyataService::class)->muatNaik($this->bankId, $fail);
    }

    public function test_kuota_habis_menyekat_muat_naik(): void
    {
        Setting::set('sp_kuota_bulanan', '0', $this->masjid);

        $this->expectException(\InvalidArgumentException::class);
        $this->muatFail();
    }

    public function test_toggle_global_off_job_gagal_tanpa_makan_kuota(): void
    {
        $batch = $this->muatFail();
        Setting::set('semak_penyata_enabled', 'off', KuotaPenyataService::MASJID_GLOBAL);

        $this->jalankanJob($batch->id);

        $this->assertSame('GAGAL', $batch->fresh()->status);
        // GAGAL tidak dikira sebagai guna.
        $this->assertSame(0, app(KuotaPenyataService::class)->usedThisMonth($this->masjid));
    }

    public function test_ai_gagal_batch_gagal_bebaskan_kuota(): void
    {
        Http::fake(['api.openai.com/*' => Http::response('ralat', 500)]);
        $batch = $this->muatFail();

        try {
            $this->jalankanJob($batch->id);
        } catch (\Throwable) {
            // handle() rethrow untuk retry — simulasi tries habis:
        }
        (new ProsesPenyataAi($batch->id))->failed(new \App\Ai\AiException('gagal'));

        $this->assertSame('GAGAL', $batch->fresh()->status);
        $this->assertSame(0, app(KuotaPenyataService::class)->usedThisMonth($this->masjid));
    }

    public function test_isolasi_masjid_lain_tidak_nampak_batch(): void
    {
        $this->fakeAi();
        $batchA = $this->muatFail();
        $this->jalankanJob($batchA->id);

        // Masjid B + bendahari B
        $masjidB = (int) Masjid::create(['nama' => 'Masjid B '.uniqid()])->id;
        app(CoaTemplateService::class)->sediaUntukMasjid($masjidB);
        $bendahariB = AppUser::create([
            'masjid_id' => $masjidB, 'login' => 'sp_bb_'.uniqid(), 'nama_penuh' => 'B',
            'role' => 'bendahari', 'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);

        app()->instance('current.masjid_id', $masjidB);

        // B tidak boleh poll status / lihat fail batch A → 404
        $this->actingAs($bendahariB)->get(route('semakpenyata.status', $batchA->id))->assertNotFound();
        $this->actingAs($bendahariB)->get(route('semakpenyata.fail', $batchA->id))->assertNotFound();

        // B tidak boleh rekod baris batch A → 404
        $lineA = BankStatementLine::where('batch_id', $batchA->id)->first();
        $this->actingAs($bendahariB)->post(route('semakpenyata.rekod', $lineA->id), [
            'coa_id' => $this->coaId('400-03010'),
        ])->assertNotFound();

        app()->instance('current.masjid_id', $this->masjid);
    }

    public function test_rekod_dua_kali_hanya_satu_jurnal(): void
    {
        $this->fakeAi();
        $batch = $this->muatFail();
        $this->jalankanJob($batch->id);

        $line = BankStatementLine::where('batch_id', $batch->id)->where('kredit', '100.00')->firstOrFail();
        $sebelum = (int) Kutipan::withoutMasjidScope()->max('id');

        app(SemakPenyataService::class)->rekodBaris($line, ['coa_id' => $this->coaId('400-03010')]);

        // Klik kedua pada baris sama → mesti ditolak, TIADA jurnal kedua.
        try {
            app(SemakPenyataService::class)->rekodBaris($line->fresh(), ['coa_id' => $this->coaId('400-03010')]);
            $this->fail('Rekod kedua sepatutnya ditolak');
        } catch (\InvalidArgumentException) {
            // dijangka
        }

        $this->assertSame(1, Kutipan::withoutMasjidScope()->where('id', '>', $sebelum)->count());
    }

    public function test_coa_keluarga_salah_ditolak_di_pelayan(): void
    {
        $this->fakeAi();
        $batch = $this->muatFail();
        $this->jalankanJob($batch->id);

        // Wang MASUK direkod ke COA belanja 600-% → mesti ditolak (P&L salah kelas).
        $line = BankStatementLine::where('batch_id', $batch->id)->where('kredit', '100.00')->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);
        app(SemakPenyataService::class)->rekodBaris($line, ['coa_id' => $this->coaId('600-06000')]);
    }

    public function test_upload_semula_selepas_gagal_guna_semula_batch(): void
    {
        // Cubaan 1: AI gagal → batch GAGAL.
        Http::fake(['api.openai.com/*' => Http::response('ralat', 500)]);
        Queue::fake();
        $fail = UploadedFile::fake()->create('penyata.pdf', 120, 'application/pdf');
        $batch = app(SemakPenyataService::class)->muatNaik($this->bankId, $fail);
        try {
            $this->jalankanJob($batch->id);
        } catch (\Throwable) {
        }
        (new ProsesPenyataAi($batch->id))->failed(new \App\Ai\AiException('gagal'));
        $this->assertSame('GAGAL', $batch->fresh()->status);

        // Cubaan 2: fail SAMA dimuat naik semula → batch DIGUNA SEMULA (bukan disekat).
        $batch2 = app(SemakPenyataService::class)->muatNaik($this->bankId, $fail);
        $this->assertSame($batch->id, $batch2->id);
        $this->assertSame('UPLOADED', $batch2->status);
        $this->assertNull($batch2->error_text);
    }

    public function test_duplikat_sah_dalam_satu_penyata_dikekalkan(): void
    {
        // Dua derma QR RM10.00 pada hari & deskripsi sama = transaksi SAH berasingan.
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode(['lines' => [
                    ['tarikh' => '2026-06-15', 'deskripsi' => 'DUITNOW QR', 'debit' => 0, 'kredit' => 10.00,
                     'cadangan_jenis' => 'KUTIPAN', 'cadangan_coa' => '400-03010', 'confidence' => 90],
                    ['tarikh' => '2026-06-15', 'deskripsi' => 'DUITNOW QR', 'debit' => 0, 'kredit' => 10.00,
                     'cadangan_jenis' => 'KUTIPAN', 'cadangan_coa' => '400-03010', 'confidence' => 90],
                ]])],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['total_tokens' => 1000],
        ])]);

        $batch = $this->muatFail();
        $this->jalankanJob($batch->id);

        $this->assertSame(2, BankStatementLine::where('batch_id', $batch->id)->count(),
            'Duplikat sah dalam SATU penyata tidak boleh digugurkan');
    }

    public function test_baris_matched_tidak_boleh_diabaikan(): void
    {
        $this->fakeAi();
        $batch = $this->muatFail();
        $this->jalankanJob($batch->id);

        $line = BankStatementLine::where('batch_id', $batch->id)->where('kredit', '100.00')->firstOrFail();
        app(SemakPenyataService::class)->rekodBaris($line, ['coa_id' => $this->coaId('400-03010')]);

        // Baris sudah MATCHED (jurnal diposkan) → abai mesti disekat.
        $this->expectException(\InvalidArgumentException::class);
        app(\App\Services\Lanjutan\ReconciliationService::class)->setStatus($line->fresh(), 'IGNORED');
    }

    public function test_openai_pdf_hantar_blok_fail(): void
    {
        $this->fakeAi();
        $batch = $this->muatFail('application/pdf');
        $this->jalankanJob($batch->id);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $content = $body['messages'][0]['content'] ?? [];
            foreach ($content as $blok) {
                if (($blok['type'] ?? '') === 'file') {
                    return true;
                }
            }

            return false;
        });
    }

    /** Batal semasa proses → BUANG hasil separa, status DIBATAL, bendera direset. */
    public function test_batal_hentikan_proses_tiada_baris(): void
    {
        $this->fakeAi();
        $batch = $this->muatFail();
        $batch->update(['batal_diminta' => true]); // pengguna minta batal

        $this->jalankanJob($batch->id);

        $batch->refresh();
        $this->assertSame('DIBATAL', $batch->status);
        $this->assertSame(0, BankStatementLine::where('batch_id', $batch->id)->count());
        $this->assertFalse((bool) $batch->batal_diminta);
    }

    /** Had kos dicapai → proses berhenti separa (SEDIA + amaran + muka_siap<jumlah). */
    public function test_had_kos_hentikan_proses_separa(): void
    {
        config()->set('spkm.penyata_text_pages_per_call', 1); // 1 muka = 1 panggilan
        Setting::set('sp_kos_per_1k_usd', '0.01', KuotaPenyataService::MASJID_GLOBAL);
        Setting::set('sp_had_usd_permintaan', '0.5', KuotaPenyataService::MASJID_GLOBAL);

        // Renderer PALSU digital: 3 muka teks.
        $this->app->instance(\App\Services\Ai\PdfRenderService::class, new class extends \App\Services\Ai\PdfRenderService {
            public function tersedia(): bool { return true; }
            public function ekstrakTeks(string $absPdfPath): array
            {
                return array_fill(0, 3, '01/02/24  INFAQ QR ....................  10.00     1,000.00');
            }
        });
        // Setiap panggilan: 1 baris + 100000 token → kos 1.0 USD (> had 0.5) selepas muka 1.
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['lines' => [
                ['tarikh' => '2024-02-01', 'deskripsi' => 'INFAQ QR', 'debit' => 0, 'kredit' => 10.00,
                 'cadangan_jenis' => 'KUTIPAN', 'cadangan_coa' => '400-03010', 'confidence' => 90],
            ]])], 'finish_reason' => 'stop']],
            'usage' => ['total_tokens' => 100000],
        ])]);

        $batch = $this->muatFail('application/pdf');
        $this->jalankanJob($batch->id);

        $batch->refresh();
        $this->assertSame('SEDIA', $batch->status);
        $this->assertSame(3, (int) $batch->muka_jumlah);
        $this->assertSame(1, (int) $batch->muka_siap);      // berhenti selepas 1 muka
        $this->assertNotNull($batch->error_text);           // amaran had kos
        $this->assertSame(1, BankStatementLine::where('batch_id', $batch->id)->count());
    }

    /** Padam batch: baris MATCHED dikekalkan (lejar utuh), lain dibuang, fail hilang. */
    public function test_padam_batch_kekalkan_baris_matched(): void
    {
        $batch = PenyataSemakan::create([
            'bank_account_id' => $this->bankId, 'file_path' => 'penyata-ai/uji.pdf', 'original_name' => 'uji.pdf',
            'mime' => 'application/pdf', 'file_hash' => hash('sha256', uniqid()), 'status' => 'SEDIA',
        ]);
        Storage::disk('local')->put('penyata-ai/uji.pdf', 'data-penyata');

        $matched = BankStatementLine::create([
            'bank_account_id' => $this->bankId, 'tarikh' => '2024-02-01', 'deskripsi' => 'SUDAH REKOD',
            'debit' => 0, 'kredit' => 10.00, 'status' => 'MATCHED', 'batch_id' => $batch->id, 'matched_voucher_id' => 999,
        ])->id;
        $unmatched = BankStatementLine::create([
            'bank_account_id' => $this->bankId, 'tarikh' => '2024-02-01', 'deskripsi' => 'BELUM',
            'debit' => 0, 'kredit' => 5.00, 'status' => 'UNMATCHED', 'batch_id' => $batch->id,
        ])->id;

        app(SemakPenyataService::class)->padamBatch($batch);

        // Batch SEDIA → jadi tombstone DIPADAM (kekal utk kuota), bukan dibuang.
        $dipadam = PenyataSemakan::withoutMasjidScope()->find($batch->id);
        $this->assertNotNull($dipadam);
        $this->assertSame('DIPADAM', $dipadam->status);
        $this->assertSame('', $dipadam->file_path);
        $this->assertFalse(Storage::disk('local')->exists('penyata-ai/uji.pdf'));
        $this->assertNull(BankStatementLine::find($unmatched));           // belum direkod → dibuang
        $kekal = BankStatementLine::find($matched);
        $this->assertNotNull($kekal);                                     // sudah direkod → kekal
        $this->assertNull($kekal->batch_id);                             // dilepas kaitan batch
    }

    /** BUG FIX: padam scan SIAP tidak memulihkan kuota (elak pintas had bulanan). */
    public function test_padam_batch_siap_kekal_kira_kuota(): void
    {
        $batch = PenyataSemakan::create([
            'bank_account_id' => $this->bankId, 'file_path' => 'penyata-ai/kuota.pdf', 'original_name' => 'kuota.pdf',
            'mime' => 'application/pdf', 'file_hash' => hash('sha256', uniqid()), 'status' => 'SEDIA',
        ]);
        Storage::disk('local')->put('penyata-ai/kuota.pdf', 'x');

        $sebelum = app(KuotaPenyataService::class)->usedThisMonth($this->masjid);
        app(SemakPenyataService::class)->padamBatch($batch);
        $selepas = app(KuotaPenyataService::class)->usedThisMonth($this->masjid);

        $this->assertSame($sebelum, $selepas, 'Padam scan siap TIDAK boleh pulihkan kuota');
        $this->assertSame('DIPADAM', PenyataSemakan::withoutMasjidScope()->find($batch->id)->status);
    }

    /** Batch GAGAL (tidak mengira kuota) → padam buang terus (tiada tombstone). */
    public function test_padam_batch_gagal_buang_terus(): void
    {
        $batch = PenyataSemakan::create([
            'bank_account_id' => $this->bankId, 'file_path' => 'x', 'original_name' => 'g.pdf',
            'mime' => 'application/pdf', 'file_hash' => hash('sha256', uniqid()), 'status' => 'GAGAL',
        ]);

        app(SemakPenyataService::class)->padamBatch($batch);

        $this->assertNull(PenyataSemakan::withoutMasjidScope()->find($batch->id));
    }

    public function test_padam_batch_sedang_proses_ditolak(): void
    {
        $batch = PenyataSemakan::create([
            'bank_account_id' => $this->bankId, 'file_path' => 'x', 'original_name' => 'x.pdf',
            'mime' => 'application/pdf', 'file_hash' => hash('sha256', uniqid()), 'status' => 'AI_PROCESSING',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(SemakPenyataService::class)->padamBatch($batch);
    }

    /** Persetujuan PDPA WAJIB — muat naik tanpa tanda persetujuan ditolak. */
    public function test_pdpa_wajib_semasa_muat_naik(): void
    {
        Queue::fake();
        $fail = UploadedFile::fake()->create('penyata.pdf', 120, 'application/pdf');

        $this->actingAs($this->bendahari)
            ->from(route('semakpenyata.index'))
            ->post(route('semakpenyata.muatnaik'), ['bank_account_id' => $this->bankId, 'fail' => $fail])
            ->assertSessionHasErrors('pdpa_setuju');

        Queue::assertNotPushed(ProsesPenyataAi::class);
    }

    /** OCR SELARI (toggle superadmin ON) → kedua-dua muka scan diproses (Http::pool). */
    public function test_ocr_selari_proses_semua_muka(): void
    {
        Setting::set('sp_ocr_selari', 'on', KuotaPenyataService::MASJID_GLOBAL);

        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sp_sel_'.uniqid();
        @mkdir($dir);
        $img1 = $dir.DIRECTORY_SEPARATOR.'muka-1.jpg';
        $img2 = $dir.DIRECTORY_SEPARATOR.'muka-2.jpg';
        file_put_contents($img1, 'JPG1');
        file_put_contents($img2, 'JPG2');

        $this->app->instance(\App\Services\Ai\PdfRenderService::class, new class($img1, $img2) extends \App\Services\Ai\PdfRenderService {
            public function __construct(private string $a, private string $b) {}
            public function tersedia(): bool { return true; }
            public function ekstrakTeks(string $absPdfPath): array { return []; } // paksa laluan SCAN
            public function renderKeImej(string $absPdfPath, string $destDir, ?int $dpi = null): array { return [$this->a, $this->b]; }
        });

        $this->fakeAi(); // setiap panggilan 2 baris
        $batch = $this->muatFail('application/pdf');
        $this->jalankanJob($batch->id);

        $batch->refresh();
        $this->assertSame('SEDIA', $batch->status);
        $this->assertSame('SCAN', $batch->kaedah);
        $this->assertSame(2, (int) $batch->muka_jumlah);
        // 2 muka × 2 baris/muka = 4 (kedua-dua muka diproses serentak).
        $this->assertSame(4, (int) $batch->bil_baris);

        @unlink($img1);
        @unlink($img2);
        @rmdir($dir);
    }

    /** PDF DIGITAL format dikenali → PARSE deterministik (TIADA AI, 0 token). */
    public function test_digital_parse_deterministik_tanpa_ai(): void
    {
        \Illuminate\Support\Facades\Http::fake(); // pastikan TIADA panggilan AI

        // Renderer PALSU: tersedia + ekstrakTeks pulang teks (kandungan tak penting —
        // parser dipalsukan di bawah).
        $this->app->instance(\App\Services\Ai\PdfRenderService::class, new class extends \App\Services\Ai\PdfRenderService {
            public function tersedia(): bool { return true; }
            public function ekstrakTeks(string $absPdfPath): array
            {
                // Mesti ≥40 aksara/muka supaya dikesan sebagai DIGITAL (bukan imbasan).
                return [
                    'Transaction Date Description Debit Credit Balance — muka penyata 1 penuh teks',
                    'Transaction Date Description Debit Credit Balance — muka penyata 2 penuh teks',
                ];
            }
        });

        // Parser PALSU: format dikenali → 1 kredit + 1 debit + grand total.
        $this->app->instance(\App\Services\Ai\PenyataDigitalParser::class, new class extends \App\Services\Ai\PenyataDigitalParser {
            public function cubaParse(string $teks): ?array
            {
                return [
                    'lines' => [
                        \App\Ai\DTO\StatementLine::fromArray(['tarikh' => '2025-02-28', 'deskripsi' => 'DuitNow QR Credit A', 'debit' => 0, 'kredit' => 10.00, 'confidence' => 100]),
                        \App\Ai\DTO\StatementLine::fromArray(['tarikh' => '2025-02-27', 'deskripsi' => 'CHEQUE PROCESSING FEE', 'debit' => 50.00, 'kredit' => 0, 'confidence' => 100]),
                    ],
                    'grand_debit' => 50.00, 'grand_credit' => 15.00, 'closing' => 965.00,
                ];
            }
        });

        $batch = $this->muatFail('application/pdf');
        $this->jalankanJob($batch->id);

        $batch->refresh();
        $this->assertSame('SEDIA', $batch->status);
        $this->assertSame('PARSE', $batch->kaedah);
        $this->assertSame(2, (int) $batch->bil_baris);
        $this->assertSame(0, (int) $batch->tokens_used);                 // TIADA token AI
        $this->assertSame('15.00', number_format((float) $batch->penyata_jum_kredit, 2));
        $this->assertSame('50.00', number_format((float) $batch->penyata_jum_debit, 2));

        \Illuminate\Support\Facades\Http::assertNothingSent();           // langsung tiada AI
    }

    /** Kos dikira TEPAT dari kadar input/output provider + pecahan token (usage). */
    public function test_kos_tepat_ikut_kadar_input_output(): void
    {
        $vault = app(SecretVaultService::class);
        \App\Models\SpProvider::create([
            'nama' => 'OpenAI Uji', 'dialect' => 'openai', 'model' => 'gpt-4o',
            'api_key_ref' => $vault->put('sk-uji-prov'), 'is_active' => true, 'is_default' => true,
            'kos_input_1k' => 0.001, 'kos_output_1k' => 0.002,
        ]);

        // usage: 4000 input + 1000 output → kos = 4000/1k×0.001 + 1000/1k×0.002 = 0.006
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['lines' => [
                ['tarikh' => '2024-02-01', 'deskripsi' => 'INFAQ', 'debit' => 0, 'kredit' => 10.00,
                 'cadangan_jenis' => 'KUTIPAN', 'cadangan_coa' => '400-03010', 'confidence' => 90],
            ]])], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 4000, 'completion_tokens' => 1000, 'total_tokens' => 5000],
        ])]);

        $batch = $this->muatFail();
        $this->jalankanJob($batch->id);

        $batch->refresh();
        $this->assertSame(4000, (int) $batch->prompt_tokens);
        $this->assertSame(1000, (int) $batch->completion_tokens);
        $this->assertSame(5000, (int) $batch->tokens_used);
        $this->assertSame('0.0060', number_format((float) $batch->cost_usd, 4));
    }

    /** Cadangan COA PINTAR ikut kata kunci deskripsi (mapping tempatan tenant). */
    public function test_cadangan_coa_pintar_kata_kunci(): void
    {
        $coaInfaq = $this->coaId('400-03010');
        \App\Models\CoaLocalMapping::create(['masjid_id' => $this->masjid, 'local_label' => 'Infaq Masjid', 'coa_id' => $coaInfaq]);

        $svc = app(\App\Services\Ai\CoaCadanganService::class);
        // "infaq" dalam deskripsi → padan mapping label "Infaq Masjid".
        $this->assertSame($coaInfaq, (int) $svc->cadangDariDeskripsi($this->masjid, 'DuitNow QR Credit AHMAD infaq', true));
        // Tiada kata kunci → null (jatuh ke fallback di pemanggil).
        $this->assertNull($svc->cadangDariDeskripsi($this->masjid, 'RANDOM TEXT XYZ', true));
    }

    /** Padam (DIPADAM) → muat naik fail SAMA dibenarkan GUNA SEMULA (bukan disekat). */
    public function test_upload_semula_selepas_dipadam_guna_semula(): void
    {
        Queue::fake();
        $fail = UploadedFile::fake()->create('penyata.pdf', 120, 'application/pdf');
        $b1 = app(SemakPenyataService::class)->muatNaik($this->bankId, $fail);
        $b1->update(['status' => 'SEDIA']);       // simulasi siap discan
        app(SemakPenyataService::class)->padamBatch($b1);
        $this->assertSame('DIPADAM', $b1->fresh()->status);

        // Fail SAMA dimuat naik semula → batch DIGUNA SEMULA (id sama, UPLOADED).
        $b2 = app(SemakPenyataService::class)->muatNaik($this->bankId, $fail);
        $this->assertSame($b1->id, $b2->id);
        $this->assertSame('UPLOADED', $b2->status);
    }

    /** Endpoint voucher → butiran rekod dalam sistem (untuk panel gelangsar). */
    public function test_voucher_endpoint_pulang_butiran(): void
    {
        $this->fakeAi();
        $batch = $this->muatFail();
        $this->jalankanJob($batch->id);
        $line = BankStatementLine::where('batch_id', $batch->id)->where('kredit', '100.00')->firstOrFail();
        $r = app(SemakPenyataService::class)->rekodBaris($line, ['coa_id' => $this->coaId('400-03010')]);

        $this->actingAs($this->bendahari)
            ->getJson(route('semakpenyata.voucher', $r['voucher_id']))
            ->assertOk()
            ->assertJsonStructure(['ref', 'tarikh', 'jumlah', 'entries', 'pautan']);
    }
}

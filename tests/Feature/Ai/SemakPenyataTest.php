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
}

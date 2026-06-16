<?php

namespace Tests\Feature\Ai;

use App\Ai\AiClientFactory;
use App\Jobs\CallAiExtraction;
use App\Jobs\ProcessDocInbox;
use App\Models\AiExtraction;
use App\Models\AiProviderConfig;
use App\Models\AppUser;
use App\Models\Attachment;
use App\Models\BankAccount;
use App\Models\DocInbox;
use App\Models\Kutipan;
use App\Models\TgBotConfig;
use App\Models\TxnDraft;
use App\Services\Ai\DraftService;
use App\Services\Security\SecretVaultService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Fasa 5 — Pipeline Telegram → AI → Draf → Pengesahan.
 * PRINSIP: AI tidak pernah pos jurnal; hanya DraftService::confirm (selepas
 * semakan bendahari) yang memanggil KutipanService/PembayaranService.
 */
class PipelineTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private const SECRET = 'uji-webhook-secret';
    private const CHAT_ID = -1009876543210;

    private AppUser $admin;
    private AppUser $bendahari;
    private AppUser $viewer;
    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
        config(['services.telegram.webhook_secret' => self::SECRET]);

        $buat = fn (string $role) => AppUser::create([
            'masjid_id' => config('sppkms.masjid_id'), 'login' => 'uji_'.$role.'_'.uniqid(),
            'nama_penuh' => 'Ujian '.$role, 'role' => $role,
            'password_hash' => Hash::make('rahsia123'), 'is_active' => 1,
        ]);
        $this->admin = $buat('admin');
        $this->bendahari = $buat('bendahari');
        $this->viewer = $buat('viewer');
        $this->bank = BankAccount::withoutMasjidScope()
            ->where('masjid_id', config('sppkms.masjid_id'))->firstOrFail();

        // Nyahaktif config sedia ada supaya config ujian sahaja dipilih (rollback selepas ujian)
        TgBotConfig::withoutMasjidScope()->where('masjid_id', config('sppkms.masjid_id'))->update(['is_active' => 0]);
        AiProviderConfig::withoutMasjidScope()->where('masjid_id', config('sppkms.masjid_id'))->update(['is_active' => 0]);

        $vault = app(SecretVaultService::class);
        TgBotConfig::create([
            'bot_token_ref' => $vault->put('123456:UJI-TOKEN-BOT'),
            'chat_id' => self::CHAT_ID,
            'default_jenis' => 'BAYARAN',
            'is_active' => 1,
        ]);
        AiProviderConfig::create([
            'provider' => 'OPENAI', 'dialect' => 'openai', 'model' => 'gpt-4o',
            'api_key_ref' => $vault->put('sk-uji-kunci-openai'),
            'supports_vision' => 1, 'is_active' => 1, 'is_default' => 1,
        ]);
    }

    // ---------- Pembantu ----------

    private function payloadFoto(int $messageId = 111, string $fileId = 'FILE-BESAR'): array
    {
        return ['update_id' => 99, 'message' => [
            'message_id' => $messageId,
            'chat' => ['id' => self::CHAT_ID],
            'from' => ['id' => 42, 'first_name' => 'Bilal'],
            'caption' => 'Resit elektrik bulan Mei',
            'photo' => [
                ['file_id' => 'kecil', 'width' => 90],
                ['file_id' => $fileId, 'width' => 1280],
            ],
        ]];
    }

    private function fakeTelegram(string $bait = 'BAIT-IMEJ-UJIAN-12345'): void
    {
        Http::fake([
            'api.telegram.org/bot*/getFile*' => Http::response(['ok' => true, 'result' => ['file_path' => 'photos/file_9.jpg']]),
            'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true]),
            'api.telegram.org/file/bot*' => Http::response($bait),
        ]);
    }

    private function fakeTelegramDanOpenAi(array $json, string $bait = 'BAIT-IMEJ-UJIAN-12345'): void
    {
        Http::fake([
            'api.telegram.org/bot*/getFile*' => Http::response(['ok' => true, 'result' => ['file_path' => 'photos/file_9.jpg']]),
            'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true]),
            'api.telegram.org/file/bot*' => Http::response($bait),
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($json)]]],
                'usage' => ['total_tokens' => 1234],
            ]),
        ]);
    }

    private function ciptaInboxDownloaded(string $kandungan = 'BAIT-IMEJ-A'): DocInbox
    {
        $inbox = DocInbox::create([
            'tg_chat_id' => self::CHAT_ID, 'tg_message_id' => random_int(100000, 999999),
            'tg_file_id' => 'FILE-X', 'tg_sender_name' => 'Bilal',
            'file_type' => 'IMAGE', 'caption' => 'Resit ujian', 'status' => 'DOWNLOADED',
        ]);
        $path = 'inbox/'.$inbox->id.'.jpg';
        Storage::disk('local')->put($path, $kandungan);
        $inbox->update(['file_path' => $path, 'file_hash' => hash('sha256', $kandungan)]);

        return $inbox->fresh();
    }

    private function jsonOpenAiSampel(): array
    {
        return [
            'doc_type' => 'BIL', 'tarikh' => '2026-06-01', 'penerima' => 'TNB BERHAD',
            'jumlah' => 154.20, 'no_rujukan' => 'TNB-998877', 'no_akaun' => '', 'bank' => 'MAYBANK',
            'kaedah' => 'EFT', 'cadangan_coa' => '600-10050', 'keterangan' => 'Bil elektrik', 'confidence' => 92,
        ];
    }

    // ---------- (a) Webhook tanpa secret ----------

    public function test_webhook_tanpa_secret_ditolak_403(): void
    {
        $this->postJson('/webhook/telegram', $this->payloadFoto())->assertStatus(403);

        $this->postJson('/webhook/telegram', $this->payloadFoto(), [
            'X-Telegram-Bot-Api-Secret-Token' => 'secret-salah',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('doc_inbox', ['tg_chat_id' => self::CHAT_ID]);
    }

    public function test_webhook_chat_id_tidak_diiktiraf_ditolak_403(): void
    {
        $payload = $this->payloadFoto();
        $payload['message']['chat']['id'] = -555000111; // bukan dalam tg_bot_config

        $this->postJson('/webhook/telegram', $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => self::SECRET,
        ])->assertStatus(403);
    }

    // ---------- (b) Webhook sah dengan photo ----------

    public function test_webhook_sah_cipta_doc_inbox_dan_dispatch_job(): void
    {
        Queue::fake();

        $this->postJson('/webhook/telegram', $this->payloadFoto(222), [
            'X-Telegram-Bot-Api-Secret-Token' => self::SECRET,
        ])->assertOk();

        $inbox = DocInbox::withoutMasjidScope()
            ->where('tg_chat_id', self::CHAT_ID)->where('tg_message_id', 222)->first();

        $this->assertNotNull($inbox);
        $this->assertSame('FILE-BESAR', $inbox->tg_file_id); // saiz terbesar dipilih
        $this->assertSame('RECEIVED', $inbox->status);
        $this->assertSame((int) config('sppkms.masjid_id'), (int) $inbox->masjid_id);

        Queue::assertPushed(ProcessDocInbox::class, fn ($job) => $job->docInboxId === $inbox->id);

        // Mesej sama dihantar semula → tiada rekod kedua
        $this->postJson('/webhook/telegram', $this->payloadFoto(222), [
            'X-Telegram-Bot-Api-Secret-Token' => self::SECRET,
        ])->assertOk();
        $this->assertSame(1, DocInbox::withoutMasjidScope()
            ->where('tg_chat_id', self::CHAT_ID)->where('tg_message_id', 222)->count());
    }

    // ---------- (c) ProcessDocInbox: muat turun + anti-pendua hash ----------

    public function test_process_doc_inbox_muat_turun_dan_kesan_pendua(): void
    {
        $this->fakeTelegram('BAIT-IMEJ-SAMA');
        Queue::fake(); // pintas chain CallAiExtraction — diuji berasingan di (d)

        $buatInbox = fn (int $msgId) => DocInbox::create([
            'tg_chat_id' => self::CHAT_ID, 'tg_message_id' => $msgId, 'tg_file_id' => 'F1',
            'file_type' => 'IMAGE', 'status' => 'RECEIVED',
        ]);

        // Pertama: dimuat turun, hash disimpan
        $pertama = $buatInbox(301);
        (new ProcessDocInbox($pertama->id))->handle();
        $pertama->refresh();

        $this->assertSame('DOWNLOADED', $pertama->status);
        $this->assertSame(hash('sha256', 'BAIT-IMEJ-SAMA'), $pertama->file_hash);
        $this->assertTrue(Storage::disk('local')->exists($pertama->file_path));
        Queue::assertPushed(CallAiExtraction::class, fn ($job) => $job->docInboxId === $pertama->id);

        // Kedua (kandungan sama) → DUPLICATE, tiada chain AI
        $kedua = $buatInbox(302);
        (new ProcessDocInbox($kedua->id))->handle();
        $kedua->refresh();

        $this->assertSame('DUPLICATE', $kedua->status);
        Queue::assertNotPushed(CallAiExtraction::class, fn ($job) => $job->docInboxId === $kedua->id);

        Storage::disk('local')->delete($pertama->file_path);
    }

    // ---------- (d) CallAiExtraction: ekstrak + draf PENDING_REVIEW ----------

    public function test_call_ai_extraction_cipta_extraction_dan_draf(): void
    {
        $this->fakeTelegramDanOpenAi($this->jsonOpenAiSampel());
        $inbox = $this->ciptaInboxDownloaded();

        (new CallAiExtraction($inbox->id))->handle(app(AiClientFactory::class));
        $inbox->refresh();

        $this->assertSame('DRAFT_CREATED', $inbox->status);

        $extraction = AiExtraction::where('inbox_id', $inbox->id)->first();
        $this->assertNotNull($extraction);
        $this->assertSame('OPENAI', $extraction->provider);
        $this->assertSame('154.20', (string) $extraction->ex_jumlah);
        $this->assertSame('600-10050', $extraction->ex_cadangan_coa);
        $this->assertSame(1234, (int) $extraction->tokens_used);

        $draf = TxnDraft::withoutMasjidScope()->where('inbox_id', $inbox->id)->first();
        $this->assertNotNull($draf);
        $this->assertSame('PENDING_REVIEW', $draf->status);
        $this->assertSame('BAYARAN', $draf->jenis); // default_jenis bot
        $this->assertSame($this->coaId('600-10050'), (int) $draf->coa_id); // COA dipetakan
        $this->assertSame('TNB BERHAD', $draf->penerima);

        // PRINSIP MUTLAK: AI tidak pos jurnal — tiada voucher untuk draf ini
        $this->assertNull($draf->posted_recno);
        $this->assertDatabaseMissing('pembayaran', ['deskripsi' => 'Bil elektrik | Resit ujian']);

        $log = \App\Models\AiCallLog::where('inbox_id', $inbox->id)->first();
        $this->assertNotNull($log);
        $this->assertTrue((bool) $log->ok);

        Storage::disk('local')->delete($inbox->file_path);
    }

    // ---------- (e) DraftService::confirm KUTIPAN → jurnal ----------

    public function test_confirm_draf_kutipan_cipta_kutipan_jurnal_dan_lampiran(): void
    {
        $this->fakeTelegram();
        $inbox = $this->ciptaInboxDownloaded('BAIT-RESIT-KUTIPAN');

        $draf = TxnDraft::create([
            'inbox_id' => $inbox->id, 'jenis' => 'KUTIPAN', 'tarikh' => '2026-06-05',
            'coa_id' => $this->coaId('400-03010'), 'jumlah' => '25.00',
            'penerima' => 'PENDERMA UJIAN', 'no_rujukan' => 'UJI-AI-K1',
            'kaedah' => 'EFT', 'deskripsi' => 'Derma melalui Telegram AI',
            'status' => 'PENDING_REVIEW',
        ]);

        $draf = app(DraftService::class)->confirm($draf, ['bank_account_id' => $this->bank->id]);

        // Kutipan tercipta + jurnal seimbang (Dr Bank / Cr Hasil)
        $kutipan = Kutipan::withoutMasjidScope()->where('no_resit', 'UJI-AI-K1')->first();
        $this->assertNotNull($kutipan);
        $this->assertSame('BANK_TRANSFER_QR', $kutipan->kaedah); // EFT dipetakan

        $entries = $kutipan->voucher()->first()->entries;
        $this->assertSame('25.00', (string) $entries->where('coa_id', (int) $this->bank->coa_id)->first()->debit);
        $this->assertSame('25.00', (string) $entries->where('coa_id', $this->coaId('400-03010'))->first()->kredit);

        // Draf POSTED + rujukan rekod + inbox CONFIRMED
        $this->assertSame('POSTED', $draf->status);
        $this->assertSame($kutipan->id, (int) $draf->posted_recno);
        $this->assertNotNull($draf->reviewed_at);
        $this->assertSame('CONFIRMED', $inbox->fresh()->status);

        // Lampiran fail asal pada kutipan
        $lampiran = Attachment::withoutMasjidScope()
            ->where('owner_type', 'KUTIPAN')->where('owner_id', $kutipan->id)->first();
        $this->assertNotNull($lampiran);
        $this->assertSame($inbox->file_path, $lampiran->file_path);

        Storage::disk('local')->delete($inbox->file_path);
    }

    // ---------- (f) Tolak draf ----------

    public function test_reject_draf_tanpa_kesan_jurnal(): void
    {
        $this->fakeTelegram();
        $inbox = $this->ciptaInboxDownloaded('BAIT-RESIT-TOLAK');

        $draf = TxnDraft::create([
            'inbox_id' => $inbox->id, 'jenis' => 'BAYARAN', 'tarikh' => '2026-06-05',
            'jumlah' => '10.00', 'status' => 'PENDING_REVIEW',
        ]);

        $draf = app(DraftService::class)->reject($draf, 'Bukan resit masjid');

        $this->assertSame('REJECTED', $draf->status);
        $this->assertNotNull($draf->reviewed_at);
        $this->assertSame('REJECTED', $inbox->fresh()->status);
        $this->assertNull($draf->posted_recno);

        Storage::disk('local')->delete($inbox->file_path);
    }

    // ---------- (g) Kawalan peranan: viewer tidak boleh sahkan ----------

    public function test_viewer_tidak_boleh_sahkan_draf(): void
    {
        $draf = TxnDraft::create([
            'jenis' => 'KUTIPAN', 'tarikh' => '2026-06-05', 'coa_id' => $this->coaId('400-03010'),
            'jumlah' => '5.00', 'kaedah' => 'TUNAI', 'status' => 'PENDING_REVIEW',
        ]);

        $this->actingAs($this->viewer)->post(route('draf.sahkan', $draf->id), [
            'jenis' => 'KUTIPAN', 'tarikh' => '2026-06-05', 'jumlah' => '5.00',
            'coa_id' => $this->coaId('400-03010'), 'kaedah' => 'TUNAI',
        ])->assertForbidden();

        $this->assertSame('PENDING_REVIEW', $draf->fresh()->status);

        // viewer masih boleh LIHAT senarai draf
        $this->actingAs($this->viewer)->get(route('draf.index'))->assertOk();
    }

    // ---------- (h) Vault: kunci API tidak plaintext dalam jadual ----------

    public function test_kunci_api_disimpan_dalam_vault_bukan_plaintext(): void
    {
        $plaintext = 'sk-RAHSIA-PLAINTEXT-TIDAK-BOLEH-BOCOR';

        $this->actingAs($this->admin)->post(route('tetapan.ai.provider'), [
            'provider' => 'ANTHROPIC', 'dialect' => 'anthropic',
            'model' => 'claude-opus-4-8', 'api_key' => $plaintext,
            'supports_vision' => 1, 'is_active' => 1,
        ])->assertRedirect(route('tetapan.ai'));

        $baris = AiProviderConfig::withoutMasjidScope()
            ->where('masjid_id', config('sppkms.masjid_id'))
            ->where('provider', 'ANTHROPIC')->orderByDesc('id')->first();

        $this->assertNotNull($baris);
        $this->assertStringStartsWith('sec_', $baris->api_key_ref);
        // Tiada kolum jadual mengandungi plaintext
        $this->assertStringNotContainsString($plaintext, json_encode($baris->getAttributes()));
        // Vault boleh nyahsulit semula nilai sebenar
        $this->assertSame($plaintext, app(SecretVaultService::class)->get($baris->api_key_ref));
        // Cipher dalam vault BUKAN plaintext
        $cipher = \DB::table('secret_vault')->where('ref', $baris->api_key_ref)->value('cipher');
        $this->assertStringNotContainsString($plaintext, (string) $cipher);
    }

    // ---------- Tambahan: AI gagal → kekal AI_PROCESSING (retry), failed() → AI_FAILED ----------

    public function test_ai_gagal_kekal_ai_processing_untuk_retry(): void
    {
        Http::fake([
            'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true]),
            'api.openai.com/*' => Http::response(['error' => ['message' => 'kuota habis']], 429),
        ]);
        $inbox = $this->ciptaInboxDownloaded('BAIT-GAGAL');

        try {
            (new CallAiExtraction($inbox->id))->handle(app(AiClientFactory::class));
            $this->fail('Sepatutnya melempar AiException untuk retry queue.');
        } catch (\App\Ai\AiException) {
            // dijangka — queue akan retry (tries 3); status kekal AI_PROCESSING supaya
            // guard handle() menerima percubaan berikutnya
        }

        // Status MESTI kekal AI_PROCESSING (bukan AI_FAILED) supaya retry berfungsi
        $this->assertSame('AI_PROCESSING', $inbox->fresh()->status);
        $log = \App\Models\AiCallLog::where('inbox_id', $inbox->id)->first();
        $this->assertFalse((bool) $log->ok);
        $this->assertSame(429, (int) $log->http_status);
        $this->assertSame(0, TxnDraft::withoutMasjidScope()->where('inbox_id', $inbox->id)->count());

        Storage::disk('local')->delete($inbox->file_path);
    }

    public function test_retry_kedua_berjaya_cipta_draf(): void
    {
        // Panggilan AI pertama gagal (429), kedua berjaya — buktikan retry berfungsi
        $okJson = ['choices' => [['message' => ['content' => json_encode($this->jsonOpenAiSampel())]]], 'usage' => ['total_tokens' => 1234]];
        Http::fake([
            'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true]),
            'api.openai.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'kuota']], 429)
                ->push($okJson, 200),
        ]);
        $inbox = $this->ciptaInboxDownloaded('BAIT-RETRY');

        try {
            (new CallAiExtraction($inbox->id))->handle(app(AiClientFactory::class));
        } catch (\App\Ai\AiException) {
            // percubaan pertama gagal
        }
        $this->assertSame('AI_PROCESSING', $inbox->fresh()->status);

        // Percubaan kedua (status masih AI_PROCESSING, guard membenarkan)
        (new CallAiExtraction($inbox->id))->handle(app(AiClientFactory::class));

        $this->assertSame('DRAFT_CREATED', $inbox->fresh()->status);
        $this->assertSame(1, TxnDraft::withoutMasjidScope()->where('inbox_id', $inbox->id)->count());

        Storage::disk('local')->delete($inbox->file_path);
    }

    public function test_failed_tanda_ai_failed_dan_notifikasi(): void
    {
        Http::fake(['api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true])]);
        $inbox = $this->ciptaInboxDownloaded('BAIT-FAILED');
        $inbox->update(['status' => 'AI_PROCESSING']);

        (new CallAiExtraction($inbox->id))->failed(new \App\Ai\AiException('kuota habis'));

        $this->assertSame('AI_FAILED', $inbox->fresh()->status);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'sendMessage'));

        Storage::disk('local')->delete($inbox->file_path);
    }

    public function test_telegram_gagal_selepas_draf_tidak_rosakkan_draf(): void
    {
        // AI berjaya, tetapi Telegram sendMessage lemparkan ConnectionException
        $okJson = ['choices' => [['message' => ['content' => json_encode($this->jsonOpenAiSampel())]]], 'usage' => ['total_tokens' => 1234]];
        Http::fake([
            'api.openai.com/*' => Http::response($okJson, 200),
            'api.telegram.org/bot*/sendMessage' => fn () => throw new \Illuminate\Http\Client\ConnectionException('rangkaian putus'),
        ]);
        $inbox = $this->ciptaInboxDownloaded('BAIT-TGFAIL');

        // TIDAK boleh throw — draf mesti selamat walau notifikasi gagal
        (new CallAiExtraction($inbox->id))->handle(app(AiClientFactory::class));

        $this->assertSame('DRAFT_CREATED', $inbox->fresh()->status);
        $this->assertSame(1, TxnDraft::withoutMasjidScope()->where('inbox_id', $inbox->id)->count());

        Storage::disk('local')->delete($inbox->file_path);
    }
}

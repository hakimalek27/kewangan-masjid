<?php

namespace Tests\Feature\Api;

use App\Models\AppUser;
use App\Models\BankAccount;
use App\Models\WebhookSubscription;
use App\Services\Ai\DraftService;
use App\Services\Lanjutan\YearEndService;
use App\Services\Transaksi\KutipanService;
use App\Services\Transaksi\PembayaranService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Membuktikan webhook dicetus untuk SEMUA asal-usul (web/draf/tutup-tahun),
 * bukan hanya API — penemuan audit #1. SendWebhook di-fake (tiada HTTP).
 */
class WebhookEventTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
        $this->bank = BankAccount::withoutMasjidScope()->where('masjid_id', config('spkm.masjid_id'))->firstOrFail();
        Queue::fake(); // pintas SendWebhook & semua job lain
    }

    private function langgan(string $event): void
    {
        WebhookSubscription::create([
            'masjid_id' => config('spkm.masjid_id'),
            'client_id' => null,
            'event' => $event,
            'target_url' => 'https://contoh.test/hook',
            'secret' => 'rahsia-hmac',
            'is_active' => 1,
        ]);
    }

    private function adaDelivery(string $event): bool
    {
        return \DB::table('webhook_delivery')->where('event', $event)->exists();
    }

    public function test_kutipan_web_cetus_transaction_created(): void
    {
        $this->langgan('transaction.created');

        app(KutipanService::class)->create([
            'tarikh' => '2026-06-12', 'coa_id' => $this->coaId('400-03010'),
            'kaedah' => 'TUNAI', 'jumlah' => '5.00', 'no_resit' => 'WH-1',
        ]);

        $this->assertTrue($this->adaDelivery('transaction.created'),
            'Kutipan melalui borang web MESTI mencetus transaction.created (bukan API sahaja).');
    }

    public function test_void_kutipan_cetus_transaction_voided(): void
    {
        $this->langgan('transaction.voided');

        $k = app(KutipanService::class)->create([
            'tarikh' => '2026-06-12', 'coa_id' => $this->coaId('400-03010'),
            'kaedah' => 'TUNAI', 'jumlah' => '5.00', 'no_resit' => 'WH-2',
        ]);
        app(KutipanService::class)->void($k->fresh(), 'ujian webhook');

        $this->assertTrue($this->adaDelivery('transaction.voided'));
    }

    public function test_bayaran_web_cetus_transaction_created(): void
    {
        $this->langgan('transaction.created');

        app(PembayaranService::class)->createBayaran([
            'tar_lulus' => '2026-06-12', 'coa_id' => $this->coaId('600-06000'),
            'jumlah' => '5.00', 'cara_bayar' => 'EFT', 'bank_account_id' => $this->bank->id,
            'baucer_no' => 'WH-PV1', 'pemohon' => 'UJIAN',
        ]);

        $this->assertTrue($this->adaDelivery('transaction.created'));
    }

    public function test_tiada_langganan_tiada_delivery(): void
    {
        // Tidak melanggan apa-apa → tiada delivery walau transaksi dicipta
        app(KutipanService::class)->create([
            'tarikh' => '2026-06-12', 'coa_id' => $this->coaId('400-03010'),
            'kaedah' => 'TUNAI', 'jumlah' => '5.00', 'no_resit' => 'WH-3',
        ]);

        $this->assertFalse($this->adaDelivery('transaction.created'));
    }

    public function test_tutup_tahun_cetus_report_closed(): void
    {
        $this->langgan('report.closed');

        // Guna tahun lampau yang ada data sejarah supaya ada baki untuk ditutup;
        // 2099 tiada data → guna tahun ujian terpencil dengan satu kutipan.
        app(KutipanService::class)->create([
            'tarikh' => '2099-03-01', 'coa_id' => $this->coaId('400-03010'),
            'kaedah' => 'TUNAI', 'jumlah' => '10.00', 'no_resit' => 'WH-YE',
        ]);

        app(YearEndService::class)->tutup(2099);

        $this->assertTrue($this->adaDelivery('report.closed'));
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\ApiClient;
use App\Models\JournalVoucher;
use App\Models\Kutipan;
use App\Models\Pembayaran;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Ujian API awam /v1 (Fasa 6) — pengesahan token, scope, idempotency,
 * endpoint baca/tulis, void, laporan, dan had kadar (API-SPEC.md).
 */
class ApiV1Test extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private const SCOPES_PENUH = 'read:transactions,write:receipts,write:payments,read:reports,read:accounts,read:balances,read:programs';

    private ApiClient $klien;
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();

        $this->secret = Str::random(40);
        $this->klien = $this->buatKlien(self::SCOPES_PENUH, $this->secret);
    }

    private function buatKlien(string $scopes, string $secret, int $rateLimit = 1000): ApiClient
    {
        return ApiClient::create([
            'masjid_id'          => config('sppkms.masjid_id'),
            'name'               => 'Ujian API '.uniqid(),
            'client_key'         => bin2hex(random_bytes(16)),
            'secret_hash'        => Hash::make($secret),
            'scopes'             => $scopes,
            'rate_limit_per_min' => $rateLimit,
            'is_active'          => 1,
        ]);
    }

    private function token(?ApiClient $klien = null, ?string $secret = null): string
    {
        $resp = $this->postJson('/v1/auth/token', [
            'client_key' => ($klien ?? $this->klien)->client_key,
            'secret'     => $secret ?? $this->secret,
        ]);
        $resp->assertOk()->assertJsonStructure(['access_token', 'token_type', 'expires_in']);

        return $resp->json('access_token');
    }

    /** @return array<string,string> */
    private function kepala(?string $token = null, ?ApiClient $klien = null): array
    {
        return [
            'Authorization' => 'Bearer '.($token ?? $this->token()),
            'X-Client-Key'  => ($klien ?? $this->klien)->client_key,
        ];
    }

    // (a) Token salah → 401 UNAUTHENTICATED
    public function test_token_salah_ditolak_401(): void
    {
        // secret salah semasa minta token
        $this->postJson('/v1/auth/token', [
            'client_key' => $this->klien->client_key,
            'secret'     => 'salah-sama-sekali',
        ])->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');

        // bearer token rawak tidak sah
        $this->withHeaders([
            'Authorization' => 'Bearer '.Str::random(64),
            'X-Client-Key'  => $this->klien->client_key,
        ])->getJson('/v1/accounts')
            ->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    // (b) Tanpa scope → 403 FORBIDDEN_SCOPE
    public function test_tanpa_scope_ditolak_403(): void
    {
        $secret = Str::random(40);
        $klienSempit = $this->buatKlien('read:reports', $secret);
        $token = $this->token($klienSempit, $secret);

        $this->withHeaders($this->kepala($token, $klienSempit))
            ->getJson('/v1/accounts')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN_SCOPE');
    }

    // (c) GET /v1/accounts OK & ada kod 250-05010
    public function test_get_accounts_berjaya(): void
    {
        $resp = $this->withHeaders($this->kepala())->getJson('/v1/accounts')->assertOk();

        $kodSemua = collect($resp->json('data'))->pluck('kod');
        $this->assertTrue($kodSemua->contains('250-05010'), 'COA 250-05010 mesti ada dalam senarai');
    }

    // (d) GET /v1/transactions dengan filter from/to → pagination
    public function test_get_transactions_dengan_filter_dan_pagination(): void
    {
        $resp = $this->withHeaders($this->kepala())
            ->getJson('/v1/transactions?from=2026-01-01&to=2026-01-31&type=receipt&limit=10')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'type', 'date', 'amount', 'coa', 'coa_name']],
                'pagination' => ['page', 'limit', 'total'],
            ]);

        $this->assertSame(1, $resp->json('pagination.page'));
        $this->assertSame(10, $resp->json('pagination.limit'));
        $this->assertGreaterThan(0, $resp->json('pagination.total'));
        $this->assertLessThanOrEqual(10, count($resp->json('data')));
        $this->assertSame('receipt', $resp->json('data.0.type'));
    }

    // (e) POST /v1/receipts tanpa Idempotency-Key → 400
    public function test_post_receipts_tanpa_idempotency_key_400(): void
    {
        $this->withHeaders($this->kepala())
            ->postJson('/v1/receipts', $this->badanResit())
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.field', 'Idempotency-Key');
    }

    // (f) POST /v1/receipts dengan key → 201 + journal seimbang + rekod DB
    public function test_post_receipts_berjaya_201_jurnal_seimbang(): void
    {
        $resp = $this->withHeaders([...$this->kepala(), 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/v1/receipts', $this->badanResit())
            ->assertStatus(201)
            ->assertJsonPath('status', 'POSTED')
            ->assertJsonStructure(['id', 'voucher_ref', 'status', 'journal', 'receipt_no']);

        $journal = collect($resp->json('journal'));
        $this->assertSame(
            number_format($journal->sum('debit'), 2),
            number_format($journal->sum('credit'), 2),
            'Jurnal mesti seimbang Dr=Cr'
        );
        $this->assertEqualsWithDelta(150.00, $journal->sum('debit'), 0.001);

        // Rekod kutipan & voucher jurnal wujud dalam DB
        $kutipan = Kutipan::withoutMasjidScope()->find($resp->json('id'));
        $this->assertNotNull($kutipan, 'Kutipan mesti wujud dalam DB');
        $this->assertSame('ACTIVE', $kutipan->status);
        $voucher = JournalVoucher::withoutMasjidScope()->where('voucher_ref', $resp->json('voucher_ref'))->first();
        $this->assertNotNull($voucher, 'Voucher jurnal mesti wujud dalam DB');
        $this->assertSame('POSTED', $voucher->status);
        $this->assertCount(2, $voucher->entries);
    }

    // (g) POST /v1/receipts ULANG key sama → respons sama, TIADA rekod kedua
    public function test_post_receipts_idempotent_tiada_rekod_berganda(): void
    {
        $kepala = [...$this->kepala(), 'Idempotency-Key' => (string) Str::uuid()];
        $badan = $this->badanResit();

        $pertama = $this->withHeaders($kepala)->postJson('/v1/receipts', $badan)->assertStatus(201);
        $kiraSelepasPertama = Kutipan::withoutMasjidScope()->count();

        $kedua = $this->withHeaders($kepala)->postJson('/v1/receipts', $badan)
            ->assertStatus(201)
            ->assertHeader('Idempotency-Replayed', 'true');

        // Respons SAMA + tiada rekod kedua (delta 0)
        $this->assertSame($pertama->json(), $kedua->json());
        $this->assertSame($kiraSelepasPertama, Kutipan::withoutMasjidScope()->count(), 'Tiada kutipan kedua dicipta');
    }

    // (g2) Key sama + BADAN berbeza → 409 CONFLICT (M2) — jangan main-semula respons salah
    public function test_idempotency_key_sama_badan_berbeza_409(): void
    {
        $key = (string) Str::uuid();
        $kepala = [...$this->kepala(), 'Idempotency-Key' => $key];

        $this->withHeaders($kepala)->postJson('/v1/receipts', $this->badanResit())->assertStatus(201);

        $badanLain = [...$this->badanResit(), 'amount' => 999.00, 'receipt_no' => 'auto'];
        $this->withHeaders($kepala)->postJson('/v1/receipts', $badanLain)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT');
    }

    // (g3) Key sama merentas ENDPOINT berbeza → tidak berlanggar (M1/E3)
    public function test_idempotency_key_sama_endpoint_berbeza_tidak_berlanggar(): void
    {
        $key = (string) Str::uuid();

        $this->withHeaders([...$this->kepala(), 'Idempotency-Key' => $key])
            ->postJson('/v1/receipts', $this->badanResit())->assertStatus(201);

        // Key SAMA tetapi endpoint /payments → mesti diproses (bukan replay resit)
        $this->withHeaders([...$this->kepala(), 'Idempotency-Key' => $key])
            ->postJson('/v1/payments', [
                'date' => '2026-06-12', 'coa' => '600-06000', 'amount' => 55.00,
                'method' => 'EFT', 'bank_slot' => 1, 'payee' => 'UJI E3',
                'voucher_no' => 'auto', 'program' => 'JAMUAN', 'description' => 'UJI E3',
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'POSTED');
    }

    // (h) POST /v1/payments → 201
    public function test_post_payments_berjaya_201(): void
    {
        $resp = $this->withHeaders([...$this->kepala(), 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/v1/payments', [
                'date'        => '2026-06-12',
                'coa'         => '600-06000',
                'amount'      => 320.00,
                'method'      => 'EFT',
                'bank_slot'   => 1,
                'payee'       => 'KATERING UJIAN API',
                'voucher_no'  => 'UJI-API-PV1',
                'program'     => 'JAMUAN',
                'description' => 'UJIAN API PEMBAYARAN',
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'POSTED')
            ->assertJsonPath('voucher_no', 'UJI-API-PV1');

        $journal = collect($resp->json('journal'));
        $this->assertSame(number_format($journal->sum('debit'), 2), number_format($journal->sum('credit'), 2));

        $bayaran = Pembayaran::withoutMasjidScope()->find($resp->json('id'));
        $this->assertNotNull($bayaran);
        $this->assertSame('BAYARAN', $bayaran->jenis);
    }

    // (i) Void → status VOIDED + voucher VOID
    public function test_void_transaksi_melalui_api(): void
    {
        $cipta = $this->withHeaders([...$this->kepala(), 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/v1/receipts', $this->badanResit())
            ->assertStatus(201);

        $id = $cipta->json('id');

        $this->withHeaders([...$this->kepala(), 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/v1/transactions/{$id}/void?type=receipt")
            ->assertOk()
            ->assertJsonPath('status', 'VOIDED')
            ->assertJsonPath('id', $id);

        $kutipan = Kutipan::withoutMasjidScope()->find($id);
        $this->assertSame('DELETED', $kutipan->status);
        $this->assertSame('VOID', $kutipan->voucher()->first()->status);
    }

    // (j) GET /v1/reports/balance-sheet cutoff 2026-06-30 → total_aset 183155.95
    public function test_balance_sheet_tally_data_sejarah(): void
    {
        $this->withHeaders($this->kepala())
            ->getJson('/v1/reports/balance-sheet?cutoff=2026-06-30')
            ->assertOk()
            ->assertJsonPath('total_aset', '183155.95')
            ->assertJsonPath('seimbang', true);
    }

    // (k) Had kadar: rate_limit_per_min=2 → panggilan ke-3 429 RATE_LIMITED
    public function test_rate_limit_429(): void
    {
        $secret = Str::random(40);
        $klienTerhad = $this->buatKlien(self::SCOPES_PENUH, $secret, rateLimit: 2);
        $token = $this->token($klienTerhad, $secret);
        $kepala = $this->kepala($token, $klienTerhad);

        $this->withHeaders($kepala)->getJson('/v1/accounts')->assertOk();
        $this->withHeaders($kepala)->getJson('/v1/accounts')->assertOk();
        $this->withHeaders($kepala)->getJson('/v1/balances')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    /** Badan POST /v1/receipts piawai (ikut contoh spec §5). */
    private function badanResit(): array
    {
        return [
            'date'        => '2026-06-12',
            'coa'         => '400-03010',
            'amount'      => 150.00,
            'method'      => 'BANK_TRANSFER',
            'bank_slot'   => 1,
            'payer'       => 'UJIAN API',
            'receipt_no'  => 'auto',
            'program'     => 'SUMBANGAN',
            'description' => 'UJIAN API RESIT',
        ];
    }
}

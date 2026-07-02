<?php

namespace App\Http\Controllers\Web\Tetapan;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use App\Models\WebhookSubscription;
use App\Services\Security\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Tetapan API Awam (Fasa 6, admin sahaja) — pengurusan klien API
 * (client_key/secret/scopes/rate limit/IP allowlist), langganan webhook,
 * dan log 100 panggilan terakhir. Secret DITUNJUK SEKALI sahaja selepas
 * cipta — hanya secret_hash disimpan.
 */
class ApiController extends Controller
{
    public const SCOPES = [
        'read:transactions', 'write:receipts', 'write:payments',
        'read:reports', 'read:accounts', 'read:balances', 'read:programs',
    ];

    public const EVENTS = [
        'transaction.created', 'transaction.voided', 'draft.created',
        'draft.confirmed', 'report.closed',
    ];

    public function __construct(private AuditTrailService $audit)
    {
    }

    public function index(): View
    {
        return view('tetapan.api-index', [
            'klien'    => ApiClient::query()->orderBy('name')->get(),
            'webhooks' => WebhookSubscription::query()->orderBy('event')->get(),
            'scopes'   => self::SCOPES,
            'events'   => self::EVENTS,
        ]);
    }

    public function klienSimpan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'               => ['required', 'string', 'max:120'],
            'scopes'             => ['required', 'array', 'min:1'],
            'scopes.*'           => ['in:'.implode(',', self::SCOPES)],
            'rate_limit_per_min' => ['required', 'integer', 'min:1', 'max:10000'],
            'ip_allowlist'       => ['nullable', 'string', 'max:255'],
        ]);

        $clientKey = bin2hex(random_bytes(16)); // 32 hex
        $secret = Str::random(40);

        $klien = ApiClient::create([
            'name'               => $data['name'],
            'client_key'         => $clientKey,
            'secret_hash'        => Hash::make($secret),
            'scopes'             => implode(',', $data['scopes']),
            'rate_limit_per_min' => $data['rate_limit_per_min'],
            'ip_allowlist'       => $data['ip_allowlist'] ?? null,
            'is_active'          => 1,
        ]);

        $this->audit->log('CREATE', 'api_client', null, [
            'name' => $data['name'], 'client_key' => $clientKey, 'scopes' => implode(',', $data['scopes']),
        ], $klien->id);

        return redirect()->route('tetapan.api')
            ->with('success', "Klien API '{$data['name']}' berjaya dicipta.")
            ->with('api_client_key', $clientKey)
            ->with('api_secret', $secret); // DITUNJUK SEKALI SAHAJA
    }

    public function klienToggle(ApiClient $client): RedirectResponse
    {
        $baru = !$client->is_active;
        $client->update(['is_active' => $baru]);

        $this->audit->log('UPDATE', 'api_client',
            ['is_active' => !$baru], ['is_active' => $baru], $client->id);

        return redirect()->route('tetapan.api')
            ->with('success', "Klien '{$client->name}' ".($baru ? 'diaktifkan' : 'dinyahaktifkan').'.');
    }

    public function webhookSimpan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'event'      => ['required', 'in:'.implode(',', self::EVENTS)],
            'target_url' => ['required', 'url', 'max:255', $this->bukanHosDalaman()],
            'secret'     => ['nullable', 'string', 'max:120'],
            'client_id'  => ['nullable', 'integer', 'exists:api_client,id'],
        ]);

        $sub = WebhookSubscription::create([...$data, 'is_active' => 1]);
        $this->audit->log('CREATE', 'webhook_subscription', null,
            ['event' => $data['event'], 'target_url' => $data['target_url']], $sub->id);

        return redirect()->route('tetapan.api')->with('success', 'Langganan webhook berjaya ditambah.');
    }

    /**
     * E9 — tolak URL yang menghala ke hos DALAMAN/tempatan (anti-SSRF): loopback,
     * link-local (169.254 — metadata cloud), julat peribadi RFC1918, dan IPv6 setara.
     */
    private function bukanHosDalaman(): \Closure
    {
        return function (string $atribut, mixed $nilai, \Closure $gagal) {
            $host = parse_url((string) $nilai, PHP_URL_HOST);
            if (! $host) {
                $gagal('URL tidak sah.');

                return;
            }

            $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $gagal('URL webhook tidak boleh menghala ke alamat dalaman/tempatan.');
            }
        };
    }

    public function webhookPadam(WebhookSubscription $subscription): RedirectResponse
    {
        $sebelum = $subscription->only(['event', 'target_url']);
        $subscription->delete();
        $this->audit->log('DELETE', 'webhook_subscription', $sebelum, null, $subscription->id);

        return redirect()->route('tetapan.api')->with('success', 'Langganan webhook dipadam.');
    }

    /** GET /tetapan/api/log — 100 panggilan API terakhir (klien masjid ini). */
    public function log(): View
    {
        $klienIds = ApiClient::query()->pluck('id');

        $log = ApiRequestLog::query()
            ->whereIn('client_id', $klienIds)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $namaKlien = ApiClient::query()->pluck('name', 'id');

        return view('tetapan.api-log', compact('log', 'namaKlien'));
    }
}

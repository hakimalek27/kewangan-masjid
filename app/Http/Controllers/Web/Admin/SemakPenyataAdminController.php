<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Masjid;
use App\Models\PenyataSemakan;
use App\Models\SpProvider;
use App\Services\Ai\KuotaPenyataService;
use App\Services\Security\AuditTrailService;
use App\Services\Security\SecretVaultService;
use App\Support\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kawalan SUPERADMIN untuk ciri "Semak Penyata (AI)":
 * kunci OpenAI PUSAT (satu untuk semua tenant, vault + masked), toggle global,
 * had kuota per-tenant (0 = mati) + top-up bulan semasa, log guna + kos token.
 * Tetapan global disimpan di app_setting sentinel masjid_id = 0.
 */
class SemakPenyataAdminController extends Controller
{
    private const G = KuotaPenyataService::MASJID_GLOBAL;

    public function __construct(
        private SecretVaultService $vault,
        private AuditTrailService $audit,
        private KuotaPenyataService $kuota,
    ) {
    }

    public function index(): View
    {
        $keyRef = Setting::get('sp_ai_key_ref', null, self::G);

        $tenants = Masjid::query()->orderBy('nama')->get()->map(fn ($m) => [
            'id' => $m->id,
            'nama' => $m->nama,
            'had' => $this->kuota->effectiveLimit($m->id),
            'topup' => $this->kuota->topupBulanIni($m->id),
            'guna' => $this->kuota->usedThisMonth($m->id),
            'baki' => $this->kuota->remaining($m->id),
        ]);

        $providers = SpProvider::orderByDesc('is_default')->orderBy('nama')->get()
            ->map(fn ($p) => (object) [
                'id' => $p->id, 'nama' => $p->nama, 'model' => $p->model,
                'base_url' => $p->base_url, 'is_active' => $p->is_active, 'is_default' => $p->is_default,
                'catatan' => $p->catatan,
                'kos_input_1k' => $p->kos_input_1k, 'kos_output_1k' => $p->kos_output_1k,
                'keyMasked' => $p->api_key_ref ? $this->vault->masked($p->api_key_ref) : null,
            ]);

        // Pantauan kos per-tenant (bulan semasa) — token & USD dibelanjakan.
        $sejakBulan = now()->startOfMonth();
        $kosTenant = PenyataSemakan::withoutMasjidScope()
            ->where('created_at', '>=', $sejakBulan)
            ->selectRaw('masjid_id, COUNT(*) bil, COALESCE(SUM(tokens_used),0) tokens, '
                .'COALESCE(SUM(prompt_tokens),0) prompt, COALESCE(SUM(completion_tokens),0) completion, '
                .'COALESCE(SUM(cost_usd),0) kos')
            ->groupBy('masjid_id')->orderByDesc('kos')->get();
        $kosTotal = (object) [
            'bil' => (int) $kosTenant->sum('bil'),
            'tokens' => (int) $kosTenant->sum('tokens'),
            'kos' => (float) $kosTenant->sum('kos'),
        ];

        return view('admin.semak-penyata', [
            'aktif' => $this->kuota->globallyEnabled(),
            'keyMasked' => $keyRef ? $this->vault->masked($keyRef) : null,
            'model' => Setting::get('sp_ai_model', 'gpt-4o', self::G),
            'baseUrl' => Setting::get('sp_ai_base_url', null, self::G),
            'kosPer1k' => Setting::get('sp_kos_per_1k_usd', null, self::G),
            'hadUsd' => Setting::get('sp_had_usd_permintaan', (string) config('spkm.penyata_had_usd_lalai'), self::G),
            'ocrSelari' => Setting::isOn('sp_ocr_selari', self::G),
            'ocrSelariBil' => (int) config('spkm.ocr_selari_bil', 5),
            'providers' => $providers,
            'aiKatalog' => config('spkm.ai_provider_catalog', []),
            'aiHarga' => config('spkm.ai_harga_model', []),
            'tenants' => $tenants,
            'kosTenant' => $kosTenant,
            'kosTotal' => $kosTotal,
            'sejakBulan' => $sejakBulan,
            'log' => PenyataSemakan::withoutMasjidScope()
                ->orderByDesc('id')
                ->paginate(30),
            'namaMasjid' => Masjid::query()->pluck('nama', 'id'),
        ]);
    }

    /** Toggle global ciri Semak Penyata. */
    public function toggle(Request $request): RedirectResponse
    {
        $aktif = (bool) $request->boolean('aktif');
        Setting::set('semak_penyata_enabled', $aktif ? 'on' : 'off', self::G);

        $this->audit->log('UPDATE', 'app_setting', null, [
            'skey' => 'semak_penyata_enabled', 'svalue' => $aktif ? 'on' : 'off',
        ]);

        return redirect()->route('admin.semakpenyata')
            ->with('success', 'Ciri Semak Penyata (AI) '.($aktif ? 'DIHIDUPKAN' : 'DIMATIKAN').' untuk semua tenant.');
    }

    /** Simpan kunci OpenAI pusat → vault (rotate kekal ref sama) + model/base_url/kos. */
    public function simpanKunci(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'max:200'],
            'model' => ['required', 'string', 'max:80'],
            'base_url' => ['nullable', 'url:https', 'max:200'],
            'kos_per_1k' => ['nullable', 'numeric', 'min:0', 'max:10'],
        ]);

        if (!empty($data['api_key'])) {
            Setting::set('sp_ai_key_ref',
                $this->vault->put($data['api_key'], Setting::get('sp_ai_key_ref', null, self::G)), self::G);
        } elseif (!Setting::get('sp_ai_key_ref', null, self::G)) {
            return back()->with('error', 'Kunci API diperlukan untuk konfigurasi pertama.');
        }

        Setting::set('sp_ai_model', $data['model'], self::G);
        Setting::set('sp_ai_base_url', $data['base_url'] ?? null, self::G);
        Setting::set('sp_kos_per_1k_usd', $data['kos_per_1k'] ?? null, self::G);

        $this->audit->log('UPDATE', 'secret_vault', null, ['skey' => 'sp_ai_key_ref/sp_ai_model']);

        return redirect()->route('admin.semakpenyata')
            ->with('success', 'Konfigurasi AI pusat disimpan (kunci dalam vault tersulit).');
    }

    /**
     * Simpan/kemas kini satu PROFIL provider Semak Penyata (OpenAI/DeepSeek/Ollama/
     * OpenRouter/custom — semua serasi-OpenAI). Kunci API → vault; hanya SATU default.
     */
    public function simpanProvider(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:sp_provider,id'],
            'nama' => ['required', 'string', 'max:80'],
            'model' => ['required', 'string', 'max:80'],
            'base_url' => ['nullable', 'url:http,https', 'max:200'],
            'api_key' => ['nullable', 'string', 'max:200'],
            'catatan' => ['nullable', 'string', 'max:200'],
            'kos_input_1k' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'kos_output_1k' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [], ['nama' => 'Nama Provider', 'model' => 'Model', 'base_url' => 'Base URL']);

        $provider = !empty($data['id']) ? SpProvider::findOrFail($data['id']) : new SpProvider();

        if (!empty($data['api_key'])) {
            $provider->api_key_ref = $this->vault->put($data['api_key'], $provider->api_key_ref);
        } elseif (!$provider->api_key_ref) {
            return back()->with('error', 'Kunci API diperlukan untuk profil provider baharu.');
        }

        $provider->fill([
            'nama' => $data['nama'],
            'dialect' => 'openai', // semua provider serasi-OpenAI buat masa ini
            'model' => $data['model'],
            'base_url' => $data['base_url'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'is_default' => $request->boolean('is_default'),
            'catatan' => $data['catatan'] ?? null,
            'kos_input_1k' => $data['kos_input_1k'] ?? null,
            'kos_output_1k' => $data['kos_output_1k'] ?? null,
        ]);
        $provider->save();

        // Satu default sahaja merentas semua profil.
        if ($provider->is_default) {
            SpProvider::where('id', '!=', $provider->id)->update(['is_default' => false]);
        }

        $this->audit->log(!empty($data['id']) ? 'UPDATE' : 'CREATE', 'sp_provider', null, [
            'id' => $provider->id, 'nama' => $provider->nama, 'model' => $provider->model,
        ], $provider->id);

        return redirect()->route('admin.semakpenyata')
            ->with('success', "Profil provider '{$provider->nama}' disimpan.");
    }

    /** Padam satu profil provider (kunci vault turut dibuang; label batch lama kekal). */
    public function padamProvider(SpProvider $provider): RedirectResponse
    {
        $nama = $provider->nama;
        if ($provider->api_key_ref) {
            $this->vault->forget($provider->api_key_ref);
        }
        $provider->delete();

        $this->audit->log('DELETE', 'sp_provider', ['nama' => $nama], null, $provider->id);

        return redirect()->route('admin.semakpenyata')
            ->with('success', "Profil provider '{$nama}' dipadam.");
    }

    /**
     * Kawalan kos & prestasi (global): had kos USD setiap permintaan scan
     * (pemutus keselamatan token) + toggle OCR IMBASAN SELARI (laju tapi guna
     * kadar/kos AI serentak lebih tinggi).
     */
    public function simpanKawalan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'had_usd' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [], ['had_usd' => 'Had Kos USD']);

        Setting::set('sp_had_usd_permintaan', (string) $data['had_usd'], self::G);
        Setting::set('sp_ocr_selari', $request->boolean('ocr_selari') ? 'on' : 'off', self::G);

        $this->audit->log('UPDATE', 'app_setting', null, [
            'skey' => 'sp_had_usd_permintaan/sp_ocr_selari',
            'had_usd' => $data['had_usd'], 'ocr_selari' => $request->boolean('ocr_selari'),
        ]);

        return redirect()->route('admin.semakpenyata')
            ->with('success', 'Kawalan kos & prestasi disimpan (had USD '.$data['had_usd'].', OCR selari '
                .($request->boolean('ocr_selari') ? 'HIDUP' : 'MATI').').');
    }

    /** Ubah had kuota bulanan satu tenant (0 = matikan ciri untuk tenant itu). */
    public function simpanKuota(Request $request, Masjid $masjid): RedirectResponse
    {
        $data = $request->validate(['kuota' => ['required', 'integer', 'min:0', 'max:1000']]);

        Setting::set('sp_kuota_bulanan', (string) $data['kuota'], $masjid->id);

        $this->audit->log('UPDATE', 'app_setting', null, [
            'skey' => 'sp_kuota_bulanan', 'masjid' => $masjid->id, 'svalue' => $data['kuota'],
        ]);

        return redirect()->route('admin.semakpenyata')
            ->with('success', "Had kuota {$masjid->nama} ditetapkan kepada {$data['kuota']}/bulan.");
    }

    /**
     * Paksa-GAGAL batch tersekat (UPLOADED/AI_PROCESSING — cth. worker mati,
     * job hilang) supaya kuota tenant dibebaskan & fail boleh dimuat naik semula.
     * Ambil TANPA skop masjid — halaman ini merentas semua tenant ({id} mentah,
     * bukan route-binding berskop).
     */
    public function gagalkan(int $id): RedirectResponse
    {
        $batch = PenyataSemakan::withoutMasjidScope()->findOrFail($id);

        if (!in_array($batch->status, ['UPLOADED', 'AI_PROCESSING'], true)) {
            return back()->with('error', "Batch #{$batch->id} berstatus {$batch->status} — hanya batch tersekat boleh digagalkan.");
        }

        $batch->update([
            'status' => 'GAGAL',
            'error_text' => 'Dibatalkan oleh pentadbir sistem (batch tersekat).',
        ]);

        $this->audit->log('UPDATE', 'penyata_semakan', ['status' => 'UPLOADED/AI_PROCESSING'],
            ['status' => 'GAGAL', 'nota' => 'paksa-gagal oleh admin'], $batch->id, masjidId: (int) $batch->masjid_id);

        return redirect()->route('admin.semakpenyata')
            ->with('success', "Batch #{$batch->id} ditanda GAGAL — kuota tenant dibebaskan.");
    }

    /** Top-up kuota bulan SEMASA sahaja untuk satu tenant. */
    public function topup(Request $request, Masjid $masjid): RedirectResponse
    {
        $data = $request->validate(['tambah' => ['required', 'integer', 'min:1', 'max:100']]);

        $kunci = 'sp_topup_'.now()->format('Y-m');
        $baru = (int) Setting::get($kunci, '0', $masjid->id) + $data['tambah'];
        Setting::set($kunci, (string) $baru, $masjid->id);

        $this->audit->log('UPDATE', 'app_setting', null, [
            'skey' => $kunci, 'masjid' => $masjid->id, 'svalue' => $baru,
        ]);

        return redirect()->route('admin.semakpenyata')
            ->with('success', "Top-up +{$data['tambah']} untuk {$masjid->nama} (bulan ini: +{$baru}).");
    }
}

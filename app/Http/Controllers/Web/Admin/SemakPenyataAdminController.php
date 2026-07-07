<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Masjid;
use App\Models\PenyataSemakan;
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

        return view('admin.semak-penyata', [
            'aktif' => $this->kuota->globallyEnabled(),
            'keyMasked' => $keyRef ? $this->vault->masked($keyRef) : null,
            'model' => Setting::get('sp_ai_model', 'gpt-4o', self::G),
            'baseUrl' => Setting::get('sp_ai_base_url', null, self::G),
            'kosPer1k' => Setting::get('sp_kos_per_1k_usd', null, self::G),
            'tenants' => $tenants,
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

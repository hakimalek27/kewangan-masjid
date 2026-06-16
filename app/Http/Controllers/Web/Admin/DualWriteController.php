<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PostDualWrite;
use App\Models\SppkmsSync;
use App\Services\Security\AuditTrailService;
use App\Services\Security\SecretVaultService;
use App\Support\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman pentadbiran Dual-Write SPPKMS (Fasa 8) — toggle on/off,
 * kredensial sistem lama (vault, papar masked), jadual status sync
 * dengan penapis + "Cuba Semula" / "Hantar Semua Tertunggak".
 */
class DualWriteController extends Controller
{
    public function __construct(
        private SecretVaultService $vault,
        private AuditTrailService $audit,
    ) {
    }

    public function index(Request $request): View
    {
        $statusPilihan = ['PENDING', 'DONE', 'FAILED', 'SKIPPED'];
        $tapis = $request->query('status');
        if (!in_array($tapis, $statusPilihan, true)) {
            $tapis = null;
        }

        $loginRef = Setting::get('sppkms_login_ref');

        $statistik = SppkmsSync::query()
            ->selectRaw('status, COUNT(*) bil')
            ->groupBy('status')
            ->pluck('bil', 'status');

        return view('admin.dualwrite', [
            'aktif'        => Setting::isOn('dual_write_sppkms'),
            'legacyUrl'    => config('sppkms.legacy_url'),
            'loginMasked'  => $loginRef ? $this->vault->masked($loginRef) : null,
            'adaPassword'  => (bool) Setting::get('sppkms_password_ref'),
            'statistik'    => $statistik,
            'tapis'        => $tapis,
            'senarai'      => SppkmsSync::query()
                ->when($tapis, fn ($q) => $q->where('status', $tapis))
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    /** Toggle dual_write_sppkms on/off. */
    public function toggle(Request $request): RedirectResponse
    {
        $aktif = (bool) $request->boolean('aktif');
        Setting::set('dual_write_sppkms', $aktif ? 'on' : 'off');

        $this->audit->log('UPDATE', 'app_setting', null, [
            'skey' => 'dual_write_sppkms', 'svalue' => $aktif ? 'on' : 'off',
        ]);

        return redirect()->route('admin.dualwrite')
            ->with('success', 'Dual-write SPPKMS '.($aktif ? 'DIHIDUPKAN' : 'DIMATIKAN').'.');
    }

    /** Simpan kredensial SPPKMS lama → vault (jadual lain simpan ref sahaja). */
    public function kredensial(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sppkms_login'    => ['required', 'string', 'max:100'],
            'sppkms_password' => ['required', 'string', 'max:200'],
        ], [], [
            'sppkms_login'    => 'login SPPKMS',
            'sppkms_password' => 'kata laluan SPPKMS',
        ]);

        Setting::set('sppkms_login_ref',
            $this->vault->put($data['sppkms_login'], Setting::get('sppkms_login_ref')));
        Setting::set('sppkms_password_ref',
            $this->vault->put($data['sppkms_password'], Setting::get('sppkms_password_ref')));

        $this->audit->log('UPDATE', 'secret_vault', null, ['skey' => 'sppkms_login_ref/sppkms_password_ref']);

        return redirect()->route('admin.dualwrite')->with('success', 'Kredensial SPPKMS disimpan dalam vault.');
    }

    /** Butang "Cuba Semula" per baris FAILED — reset PENDING + dispatch. */
    public function cubaSemula(SppkmsSync $sync): RedirectResponse
    {
        if ($sync->status !== 'FAILED') {
            return redirect()->route('admin.dualwrite')
                ->with('error', 'Hanya baris FAILED boleh dicuba semula.');
        }

        $sync->update(['status' => 'PENDING']);
        PostDualWrite::dispatch($sync->id);

        return redirect()->route('admin.dualwrite')
            ->with('success', "Sync #{$sync->id} ({$sync->source_type} #{$sync->source_id}) dihantar semula ke barisan.");
    }

    /** Butang "Hantar Semua Tertunggak" — dispatch semula semua PENDING. */
    public function tertunggak(): RedirectResponse
    {
        $ids = SppkmsSync::where('status', 'PENDING')->pluck('id');
        $ids->each(fn ($id) => PostDualWrite::dispatch((int) $id));

        return redirect()->route('admin.dualwrite')
            ->with('success', $ids->count().' item tertunggak dihantar ke barisan sync.');
    }
}

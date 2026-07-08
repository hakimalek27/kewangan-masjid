<?php

namespace App\Http\Controllers\Web\Tetapan;

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
 * Dual-Write SPPKMS — TETAPAN TENANT (bendahari/pentadbir masjid sendiri).
 * Setiap masjid masukkan kredensial login SPPKMS SENDIRI + toggle; sistem
 * menghantar salinan setiap transaksi ke sistem lama (mesrasuci.com) di bawah
 * akaun masjid itu. Semua tetapan & jadual sync BERSKOP masjid semasa
 * (Setting per masjid_id + SppkmsSync BelongsToMasjid). Superadmin (penyedia)
 * TIDAK menguruskan dual-write — ini fungsi tenant.
 */
class DualWriteController extends Controller
{
    public function __construct(
        private SecretVaultService $vault,
        private AuditTrailService $audit,
    ) {
    }

    private function masjidId(): int
    {
        return (int) app('current.masjid_id');
    }

    public function index(Request $request): View
    {
        $mid = $this->masjidId();

        $statusPilihan = ['PENDING', 'DONE', 'FAILED', 'SKIPPED'];
        $tapis = $request->query('status');
        if (!in_array($tapis, $statusPilihan, true)) {
            $tapis = null;
        }

        $loginRef = Setting::get('sppkms_login_ref', null, $mid);

        $statistik = SppkmsSync::query()
            ->selectRaw('status, COUNT(*) bil')
            ->groupBy('status')
            ->pluck('bil', 'status');

        return view('tetapan.dualwrite', [
            'aktif'        => Setting::isOn('dual_write_sppkms', $mid),
            'legacyUrl'    => config('spkm.legacy_url'),
            'loginMasked'  => $loginRef ? $this->vault->masked($loginRef) : null,
            'adaPassword'  => (bool) Setting::get('sppkms_password_ref', null, $mid),
            'statistik'    => $statistik,
            'tapis'        => $tapis,
            'senarai'      => SppkmsSync::query()
                ->when($tapis, fn ($q) => $q->where('status', $tapis))
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    /** Toggle dual_write_sppkms on/off — masjid semasa sahaja. */
    public function toggle(Request $request): RedirectResponse
    {
        $mid = $this->masjidId();
        $aktif = (bool) $request->boolean('aktif');
        Setting::set('dual_write_sppkms', $aktif ? 'on' : 'off', $mid);

        $this->audit->log('UPDATE', 'app_setting', null, [
            'skey' => 'dual_write_sppkms', 'svalue' => $aktif ? 'on' : 'off', 'masjid' => $mid,
        ]);

        return redirect()->route('tetapan.dualwrite')
            ->with('success', 'Dual-write SPPKMS '.($aktif ? 'DIHIDUPKAN' : 'DIMATIKAN').' untuk masjid ini.');
    }

    /** Simpan kredensial SPPKMS masjid ini → vault (jadual lain simpan ref sahaja). */
    public function kredensial(Request $request): RedirectResponse
    {
        $mid = $this->masjidId();
        $data = $request->validate([
            'sppkms_login'    => ['required', 'string', 'max:100'],
            'sppkms_password' => ['required', 'string', 'max:200'],
        ], [], [
            'sppkms_login'    => 'login SPPKMS',
            'sppkms_password' => 'kata laluan SPPKMS',
        ]);

        Setting::set('sppkms_login_ref',
            $this->vault->put($data['sppkms_login'], Setting::get('sppkms_login_ref', null, $mid)), $mid);
        Setting::set('sppkms_password_ref',
            $this->vault->put($data['sppkms_password'], Setting::get('sppkms_password_ref', null, $mid)), $mid);

        $this->audit->log('UPDATE', 'secret_vault', null, ['skey' => 'sppkms_login_ref/sppkms_password_ref', 'masjid' => $mid]);

        return redirect()->route('tetapan.dualwrite')->with('success', 'Kredensial SPPKMS masjid ini disimpan dalam vault.');
    }

    /** Butang "Cuba Semula" per baris FAILED — reset PENDING + dispatch (baris masjid ini sahaja). */
    public function cubaSemula(SppkmsSync $sync): RedirectResponse
    {
        if ($sync->status !== 'FAILED') {
            return redirect()->route('tetapan.dualwrite')
                ->with('error', 'Hanya baris FAILED boleh dicuba semula.');
        }

        $sync->update(['status' => 'PENDING']);
        PostDualWrite::dispatch($sync->id);

        return redirect()->route('tetapan.dualwrite')
            ->with('success', "Sync #{$sync->id} ({$sync->source_type} #{$sync->source_id}) dihantar semula ke barisan.");
    }

    /** Butang "Hantar Semua Tertunggak" — dispatch semula semua PENDING masjid ini. */
    public function tertunggak(): RedirectResponse
    {
        $ids = SppkmsSync::where('status', 'PENDING')->pluck('id');
        $ids->each(fn ($id) => PostDualWrite::dispatch((int) $id));

        return redirect()->route('tetapan.dualwrite')
            ->with('success', $ids->count().' item tertunggak dihantar ke barisan sync.');
    }
}

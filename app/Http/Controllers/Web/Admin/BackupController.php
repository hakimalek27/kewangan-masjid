<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunBackupItem;
use App\Jobs\RunDailyDbDump;
use App\Models\BackupConfig;
use App\Models\BackupLog;
use App\Models\BackupQueue;
use App\Services\Security\AuditTrailService;
use App\Services\Security\SecretVaultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Konfigurasi & status backup Google Drive (Fasa 7).
 * Fail service account JSON → storage/app/private/secure/ dan LALUANNYA
 * disimpan tersulit dalam vault (backup_config.service_account_ref).
 */
class BackupController extends Controller
{
    public function __construct(
        private SecretVaultService $vault,
        private AuditTrailService $audit,
    ) {
    }

    public function index(): View
    {
        $masjidId = (int) app('current.masjid_id');
        $config = BackupConfig::withoutMasjidScope()->find($masjidId);

        return view('admin.backup', [
            'config'      => $config,
            'adaSaJson'   => (bool) ($config?->service_account_ref && $this->vault->get($config->service_account_ref)),
            'logs'        => BackupLog::orderByDesc('id')->limit(20)->get(),
            'bilPending'  => BackupQueue::where('status', 'PENDING')->count(),
            'bilFailed'   => BackupQueue::where('status', 'FAILED')->count(),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'gdrive_folder_id' => ['nullable', 'string', 'max:120'],
            'backup_mode'      => ['required', 'array', 'min:1'],
            'backup_mode.*'    => ['in:PER_TRANSAKSI,HARIAN,LOG'],
            'retention_days'   => ['required', 'integer', 'min:30', 'max:36500'],
            'is_active'        => ['nullable', 'boolean'],
            'sa_json'          => ['nullable', 'file', 'max:64', 'mimetypes:application/json,text/plain'],
        ], [], [
            'gdrive_folder_id' => 'ID folder Google Drive',
            'backup_mode'      => 'mod backup',
            'retention_days'   => 'tempoh simpanan (hari)',
            'sa_json'          => 'fail service account JSON',
        ]);

        $masjidId = (int) app('current.masjid_id');
        $config = BackupConfig::withoutMasjidScope()->find($masjidId);

        $saRef = $config?->service_account_ref;
        if ($request->hasFile('sa_json')) {
            $kandungan = (string) $request->file('sa_json')->get();
            json_decode($kandungan, flags: JSON_THROW_ON_ERROR); // sahkan JSON tulen

            $relPath = 'secure/gdrive-sa-'.$masjidId.'.json';
            \Illuminate\Support\Facades\Storage::disk('local')->put($relPath, $kandungan);

            // simpan LALUAN fail dalam vault (bukan kandungan dalam jadual)
            $saRef = $this->vault->put(storage_path('app/private/'.$relPath), $saRef);
        }

        BackupConfig::withoutMasjidScope()->updateOrCreate(
            ['masjid_id' => $masjidId],
            [
                'provider'            => 'GDRIVE',
                'gdrive_folder_id'    => $data['gdrive_folder_id'] ?? null,
                'service_account_ref' => $saRef,
                'backup_mode'         => implode(',', $data['backup_mode']),
                'encrypt'             => 1,
                'retention_days'      => $data['retention_days'],
                'is_active'           => (bool) ($data['is_active'] ?? false),
            ]
        );

        $this->audit->log('UPDATE', 'backup_config', null, [
            'mode'      => implode(',', $data['backup_mode']),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'folder'    => $data['gdrive_folder_id'] ?? null,
        ], $masjidId);

        return redirect()->route('admin.backup')->with('success', 'Konfigurasi backup disimpan.');
    }

    /** Butang "Backup Sekarang" — dump DB penuh serta-merta. */
    public function sekarang(): RedirectResponse
    {
        RunDailyDbDump::dispatch();

        return redirect()->route('admin.backup')
            ->with('success', 'Backup DB dimulakan — semak status dalam senarai di bawah.');
    }

    /** Butang "Proses Tertunggak" — dispatch semula semua item PENDING. */
    public function tertunggak(): RedirectResponse
    {
        $ids = BackupQueue::where('status', 'PENDING')->pluck('id');
        $ids->each(fn ($id) => RunBackupItem::dispatch((int) $id));

        return redirect()->route('admin.backup')
            ->with('success', $ids->count().' item tertunggak dihantar ke barisan backup.');
    }
}

<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppUser;
use App\Models\AuditTrail;
use App\Models\BackupLog;
use App\Models\ErrorLog;
use App\Models\JournalVoucher;
use App\Models\LoginAttempt;
use App\Models\SecurityEvent;
use App\Models\TxnDraft;
use Illuminate\View\View;

/**
 * Dashboard pemantauan pentadbir (Fasa 7) — kesihatan sistem sepintas lalu:
 * transaksi, ralat, keselamatan, backup, draf AI & log masuk gagal.
 */
class PemantauanController extends Controller
{
    public function index(): View
    {
        $kad = [
            'transaksi_hari_ini' => JournalVoucher::whereDate('created_at', now()->toDateString())->count(),
            'ralat_7_hari'       => ErrorLog::where('resolved', 0)
                ->where('created_at', '>=', now()->subDays(7))->count(),
            'event_high_7_hari'  => SecurityEvent::whereIn('severity', ['HIGH', 'CRITICAL'])
                ->where('created_at', '>=', now()->subDays(7))->count(),
            'draf_ai_menunggu'   => TxnDraft::where('status', 'PENDING_REVIEW')->count(),
            'login_gagal_24j'    => LoginAttempt::where('success', 0)
                ->where('created_at', '>=', now()->subDay())->count(),
        ];

        $backupTerakhir = BackupLog::orderByDesc('id')->first();

        $auditTerkini = AuditTrail::orderByDesc('id')->limit(20)->get();
        $eventTerkini = SecurityEvent::orderByDesc('id')->limit(10)->get();

        $namaPengguna = AppUser::query()
            ->whereIn('id', $auditTerkini->pluck('user_id')->merge($eventTerkini->pluck('user_id'))->filter()->unique())
            ->pluck('nama_penuh', 'id');

        return view('admin.pemantauan', compact('kad', 'backupTerakhir', 'auditTerkini', 'eventTerkini', 'namaPengguna'));
    }
}

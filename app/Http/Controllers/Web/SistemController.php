<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AppUser;
use App\Models\Approval;
use App\Models\BackupLog;
use App\Models\BankAccount;
use App\Models\Coa;
use App\Models\ErrorLog;
use App\Models\JournalVoucher;
use App\Models\LoginAttempt;
use App\Models\Masjid;
use App\Models\SecurityEvent;
use App\Models\TxnDraft;
use Illuminate\View\View;

/**
 * Konsol Sistem — pendaratan ADMIN (pentadbir platform). Pandangan MERENTAS semua
 * masjid + kesihatan sistem. Admin TIDAK merekod kewangan mana-mana masjid (boleh
 * "Masuk" untuk LIHAT). Berbeza daripada Dashboard kewangan satu-masjid.
 */
class SistemController extends Controller
{
    public function index(): View
    {
        $masjids = Masjid::orderBy('nama')->get(['id', 'nama', 'kategori', 'negeri']);

        // Kiraan per-masjid (bebas-skop — admin merentas penyewa).
        $bilPengguna = AppUser::selectRaw('masjid_id, COUNT(*) c')->groupBy('masjid_id')->pluck('c', 'masjid_id');
        $bilCoa = Coa::withoutMasjidScope()->selectRaw('masjid_id, COUNT(*) c')->groupBy('masjid_id')->pluck('c', 'masjid_id');
        $bilBank = BankAccount::withoutMasjidScope()->where('status', 'AKTIF')
            ->selectRaw('masjid_id, COUNT(*) c')->groupBy('masjid_id')->pluck('c', 'masjid_id');
        $txnAkhir = JournalVoucher::withoutMasjidScope()->selectRaw('masjid_id, MAX(created_at) t')
            ->groupBy('masjid_id')->pluck('t', 'masjid_id');

        $kad = [
            'jumlah_masjid'     => $masjids->count(),
            'ralat_belum'       => ErrorLog::withoutMasjidScope()->where('resolved', 0)->count(),
            'event_high_7hari'  => SecurityEvent::withoutMasjidScope()->whereIn('severity', ['HIGH', 'CRITICAL'])
                ->where('created_at', '>=', now()->subDays(7))->count(),
            'draf_menunggu'     => TxnDraft::withoutMasjidScope()->where('status', 'PENDING_REVIEW')->count(),
            'kelulusan_pending' => Approval::withoutMasjidScope()->where('status', 'PENDING')->count(),
            'login_gagal_24j'   => LoginAttempt::where('success', 0)->where('created_at', '>=', now()->subDay())->count(),
        ];

        return view('sistem.console', [
            'masjids'        => $masjids,
            'bilPengguna'    => $bilPengguna,
            'bilCoa'         => $bilCoa,
            'bilBank'        => $bilBank,
            'txnAkhir'       => $txnAkhir,
            'kad'            => $kad,
            'backupTerakhir' => BackupLog::withoutMasjidScope()->orderByDesc('id')->first(),
        ]);
    }
}

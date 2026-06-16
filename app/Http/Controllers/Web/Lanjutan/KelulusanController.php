<?php

namespace App\Http\Controllers\Web\Lanjutan;

use App\Http\Controllers\Controller;
use App\Models\AppUser;
use App\Models\Approval;
use App\Services\Lanjutan\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Fasa 9 — Kelulusan Maker-Checker (/kelulusan, admin & pengerusi).
 * Senarai permohonan PENDING → Lulus (mainkan payload via PembayaranService)
 * atau Tolak.
 */
class KelulusanController extends Controller
{
    public function __construct(private ApprovalService $servis)
    {
    }

    public function index(): View
    {
        $pending = Approval::query()->where('status', 'PENDING')->orderBy('id')->get();
        $sejarah = Approval::query()->where('status', '!=', 'PENDING')->orderByDesc('decided_at')->limit(30)->get();

        $pengguna = AppUser::query()
            ->whereIn('id', $pending->pluck('maker_id')->merge($sejarah->pluck('maker_id'))->merge($sejarah->pluck('checker_id'))->filter()->unique())
            ->get(['id', 'nama_penuh', 'login'])->keyBy('id');

        return view('lanjutan.kelulusan', [
            'pending'  => $pending,
            'sejarah'  => $sejarah,
            'pengguna' => $pengguna,
            'had'      => $this->servis->had(),
        ]);
    }

    public function lulus(Approval $approval): RedirectResponse
    {
        try {
            $pembayaran = $this->servis->lulus($approval);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['kelulusan' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return back()->withErrors(['kelulusan' => 'Kelulusan gagal: '.$e->getMessage()]);
        }

        return redirect()->route('kelulusan.index')->with('success',
            "Permohonan #{$approval->id} diluluskan — pembayaran #{$pembayaran->id} (baucer {$pembayaran->baucer_no}) direkodkan.");
    }

    public function tolak(Request $request, Approval $approval): RedirectResponse
    {
        $sebab = (string) $request->input('sebab', '');

        try {
            $this->servis->tolak($approval, mb_substr($sebab, 0, 200));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['kelulusan' => $e->getMessage()]);
        }

        return redirect()->route('kelulusan.index')->with('success', "Permohonan #{$approval->id} ditolak.");
    }
}

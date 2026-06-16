<?php

namespace App\Http\Controllers\Web\Tetapan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tetapan\KataLaluanRequest;
use App\Services\Security\AuditTrailService;
use App\Services\Security\SecurityEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/** Tukar Kata Laluan (replika doResetPassword.php) — minimum 6 aksara. */
class KataLaluanController extends Controller
{
    public function __construct(
        private AuditTrailService $audit,
        private SecurityEventService $security,
    ) {
    }

    public function borang(): View
    {
        return view('tetapan.katalaluan');
    }

    public function kemaskini(KataLaluanRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (!Hash::check($request->input('kata_semasa'), $user->password_hash)) {
            return back()->withErrors(['kata_semasa' => 'Kata laluan semasa tidak betul.']);
        }

        $user->update(['password_hash' => Hash::make($request->input('kata_baharu'))]);

        $this->audit->log('UPDATE', 'app_user', null, ['tindakan' => 'tukar_kata_laluan'], $user->id);
        $this->security->log('CONFIG_CHANGE', 'Kata laluan ditukar oleh: '.$user->login, 'MEDIUM', $user->id);

        return redirect()->route('tetapan.katalaluan')
            ->with('success', 'Kata laluan dikemaskini. Sila log masuk semula selepas log keluar.');
    }
}

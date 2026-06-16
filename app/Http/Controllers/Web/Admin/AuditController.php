<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppUser;
use App\Models\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * Jejak audit hash-chain (Fasa 7) — boleh tapis, papar before/after JSON,
 * dan sahkan keutuhan rantai (sppkms:verify-audit-chain) terus dari UI.
 */
class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $audit = AuditTrail::query()
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('entity'), fn ($q) => $q->where('entity', $request->string('entity')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('dari'), fn ($q) => $q->where('created_at', '>=', $request->date('dari')->startOfDay()))
            ->when($request->filled('hingga'), fn ($q) => $q->where('created_at', '<=', $request->date('hingga')->endOfDay()))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.audit', [
            'audit'     => $audit,
            'pengguna'  => AppUser::orderBy('nama_penuh')->get(['id', 'nama_penuh']),
            'entities'  => AuditTrail::query()->distinct()->orderBy('entity')->pluck('entity'),
            'actions'   => AuditTrail::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    /** Butang "Sahkan Rantai" — jalankan verify-audit-chain, papar output. */
    public function sahkan(): RedirectResponse
    {
        $exit = Artisan::call('sppkms:verify-audit-chain');
        $output = trim(Artisan::output());

        return redirect()->route('admin.audit')
            ->with($exit === 0 ? 'success' : 'audit_gagal', $exit === 0
                ? 'Rantai audit DISAHKAN UTUH — '.$output
                : 'AMARAN: rantai audit TIDAK utuh!')
            ->with('audit_output', $output);
    }
}

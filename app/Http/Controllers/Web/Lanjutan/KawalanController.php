<?php

namespace App\Http\Controllers\Web\Lanjutan;

use App\Http\Controllers\Controller;
use App\Services\Lanjutan\ApprovalService;
use App\Services\Security\AuditTrailService;
use App\Services\Security\SecurityEventService;
use App\Support\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fasa 9 — Kawalan Dalaman (/tetapan/kawalan, admin sahaja):
 *   - approval_threshold  : had RM maker-checker (0 = mati)
 *   - fund_deficit_alert  : amaran defisit dana harian (on/off)
 *   - budget_warning      : amaran belanjawan pada borang perbelanjaan (on/off)
 *   - baki_rendah_ambang  : ambang RM notifikasi baki bank rendah (0 = mati)
 */
class KawalanController extends Controller
{
    public function __construct(
        private AuditTrailService $audit,
        private SecurityEventService $security,
    ) {
    }

    public function index(): View
    {
        return view('lanjutan.kawalan', [
            'threshold'         => Setting::get(ApprovalService::KEY_THRESHOLD, '0'),
            'fundDeficitAlert'  => Setting::get('fund_deficit_alert', 'on'),
            'budgetWarning'     => Setting::get('budget_warning', 'on'),
            'bakiRendahAmbang'  => Setting::get('baki_rendah_ambang', '0'),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'approval_threshold' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'fund_deficit_alert' => ['nullable', 'boolean'],
            'budget_warning'     => ['nullable', 'boolean'],
            'baki_rendah_ambang' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ], [], [
            'approval_threshold' => 'Had Kelulusan (RM)',
            'baki_rendah_ambang' => 'Ambang Baki Rendah (RM)',
        ]);

        $sebelum = [
            'approval_threshold' => Setting::get(ApprovalService::KEY_THRESHOLD, '0'),
            'fund_deficit_alert' => Setting::get('fund_deficit_alert', 'on'),
            'budget_warning'     => Setting::get('budget_warning', 'on'),
            'baki_rendah_ambang' => Setting::get('baki_rendah_ambang', '0'),
        ];

        $selepas = [
            'approval_threshold' => number_format((float) $data['approval_threshold'], 2, '.', ''),
            'fund_deficit_alert' => empty($data['fund_deficit_alert']) ? 'off' : 'on',
            'budget_warning'     => empty($data['budget_warning']) ? 'off' : 'on',
            'baki_rendah_ambang' => number_format((float) $data['baki_rendah_ambang'], 2, '.', ''),
        ];

        foreach ($selepas as $k => $v) {
            Setting::set($k === 'approval_threshold' ? ApprovalService::KEY_THRESHOLD : $k, $v);
        }

        $this->audit->log('UPDATE', 'app_setting', $sebelum, $selepas);
        $this->security->log('CONFIG_CHANGE',
            'Kawalan dalaman dikemaskini: had kelulusan RM'.$selepas['approval_threshold'], 'MEDIUM');

        return redirect()->route('kawalan.index')->with('success', 'Tetapan kawalan dalaman disimpan.');
    }
}

<?php

use App\Jobs\PruneBackups;
use App\Jobs\RunDailyDbDump;
use App\Jobs\UploadAuditLogOffsite;
use App\Models\ErrorLog;
use App\Models\FdInvestment;
use App\Services\Integration\AlertService;
use App\Services\Security\SecurityEventService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Penjadual Fasa 7 — backup luar tapak & pemantauan integriti
|--------------------------------------------------------------------------
| Jalankan worker penjadual: php artisan schedule:work (atau cron schedule:run)
| dan worker barisan:        php artisan queue:work --queue=ai,webhook,backup,sync
*/

// 02:00 — dump penuh DB → sulit → Google Drive (mod HARIAN)
Schedule::job(new RunDailyDbDump)->dailyAt('02:00')->name('backup-db-harian');

// Setiap jam — eksport audit_trail + security_event 24j terakhir (mod LOG)
Schedule::job(new UploadAuditLogOffsite)->hourly()->name('backup-log-audit');

// 03:30 — retensi: padam backup Drive + fail payload tempatan melepasi
// backup_config.retention_days (selepas dump harian 02:00 selesai)
Schedule::job(new PruneBackups)->dailyAt('03:30')->name('prune-backup-retensi');

// Mingguan (Isnin 05:00) — uji-pulih backup terkini (E6): muat turun + sahkan
// checksum & boleh nyahsulit; alert jika backup rosak/tak boleh dipulih.
Schedule::command('sppkms:uji-pulih-backup')->weeklyOn(1, '05:00')->name('uji-pulih-backup');

// 04:00 — sapu fail lampiran YATIM (bayaran maker-checker distash tetapi permohonan
// tak pernah diputuskan): tiada row Attachment & bukan permohonan PENDING, umur >7 hari.
Schedule::command('sppkms:sapu-lampiran')->dailyAt('04:00')->name('sapu-lampiran-yatim');

// 06:30 — semakan integriti: double-entry seimbang + hash-chain audit utuh.
// Gagal → security_event INTEGRITY_FAIL (CRITICAL) + amaran Telegram.
Schedule::call(function () {
    $semakan = [
        'sppkms:verify-balance'     => 'Imbangan double-entry (Σdebit = Σkredit)',
        'sppkms:verify-audit-chain' => 'Hash-chain jejak audit',
    ];

    foreach ($semakan as $command => $label) {
        if (Artisan::call($command) !== 0) {
            $output = trim(Artisan::output());

            app(SecurityEventService::class)->log(
                'INTEGRITY_FAIL',
                "{$label} GAGAL ({$command}): ".mb_substr($output, 0, 350),
                'CRITICAL'
            );

            app(AlertService::class)->hantarSemua(
                "🚨 SEMAKAN INTEGRITI GAGAL\n{$label}\nCommand: {$command}\n"
                .mb_substr($output, 0, 500)
            );
        }
    }
})->dailyAt('06:30')->name('semak-integriti-harian');

// 07:00 — FD hampir matang (≤ 30 hari): notis error_log INFO + Telegram
Schedule::call(function () {
    $senarai = FdInvestment::withoutMasjidScope()
        ->whereIn('status', ['AKTIF', 'DIPERBAHARUI'])
        ->whereNotNull('maturity_date')
        ->whereBetween('maturity_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
        ->get();

    foreach ($senarai->groupBy('masjid_id') as $masjidId => $fds) {
        $baris = $fds->map(fn ($fd) => sprintf(
            '- %s (RM%s) matang %s',
            $fd->institusi,
            number_format((float) $fd->jumlah, 2),
            $fd->maturity_date->format('d/m/Y')
        ))->implode("\n");

        ErrorLog::withoutMasjidScope()->create([
            'masjid_id' => $masjidId,
            'level'     => 'INFO',
            'message'   => mb_substr('FD hampir matang (≤30 hari): '.$fds->count().' sijil. '.$baris, 0, 500),
        ]);

        app(AlertService::class)->hantar(
            (int) $masjidId,
            "📌 PERINGATAN PELABURAN (FD)\n{$fds->count()} sijil matang dalam 30 hari:\n{$baris}"
        );
    }
})->dailyAt('07:00')->name('semak-fd-matang');

/*
|--------------------------------------------------------------------------
| Penjadual Fasa 9 — amaran defisit dana/tabung
|--------------------------------------------------------------------------
| 07:30 — dana berbaki negatif tanpa allow_deficit → security_event
| FUND_DEFICIT (HIGH) SEKALI SEHARI per masjid + Telegram best-effort.
| Boleh dimatikan melalui /tetapan/kawalan (Setting 'fund_deficit_alert').
*/
Schedule::call(function () {
    app(\App\Services\Lanjutan\FundService::class)->semakDefisitSemua();
})->dailyAt('07:30')->name('semak-defisit-dana');

// 08:00 — notifikasi baki bank rendah (ambang Setting 'baki_rendah_ambang';
// 0 = mati). ErrorLog INFO sekali sehari per masjid + Telegram best-effort.
Schedule::call(function () {
    app(\App\Services\Lanjutan\BakiRendahService::class)->semakSemua();
})->dailyAt('08:00')->name('semak-baki-rendah');

/*
|--------------------------------------------------------------------------
| Penjadual Fasa 9 — susut nilai bulanan berjadual (idempoten)
|--------------------------------------------------------------------------
| 01hb setiap bulan 01:00 — jana & pos susut nilai bulan SEMASA bagi semua
| aset aktif setiap masjid. DepreciationService::janaBulan idempoten —
| aset yang sudah diposkan bulan itu dilangkau (depreciation_schedule).
*/
Schedule::call(function () {
    $tahun = (int) now()->year;
    $bulan = (int) now()->month;
    $servis = app(\App\Services\Lanjutan\DepreciationService::class);

    foreach (\App\Models\Masjid::pluck('id') as $masjidId) {
        app()->instance('current.masjid_id', (int) $masjidId);

        try {
            $servis->janaBulan($tahun, $bulan, (int) $masjidId);
        } catch (\Throwable $e) {
            ErrorLog::withoutMasjidScope()->create([
                'masjid_id' => (int) $masjidId,
                'level'     => 'ERROR',
                'message'   => mb_substr('Susut nilai bulanan gagal: '.$e->getMessage(), 0, 500),
            ]);
        }
    }
})->monthlyOn(1, '01:00')->name('susut-nilai-bulanan');

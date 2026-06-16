<?php
// Verifikasi pemasangan segar: laporan mesti tally dengan fixture.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

app()->instance('current.masjid_id', 49);

echo 'Voucher: '.\App\Models\JournalVoucher::withoutMasjidScope()->count().PHP_EOL;

$bs = app(\App\Services\Laporan\ReportService::class)->balanceSheet('2026-06', 49);
echo 'BS aset: '.$bs['total_aset'].' | ekuiti: '.$bs['total_ekuiti'].' | seimbang: '.($bs['seimbang'] ? 'YA' : 'TIDAK').PHP_EOL;

$pl = app(\App\Services\Laporan\ReportService::class)->profitLoss('2024-01', '2024-12', 49);
echo 'P&L 2024: '.$pl['jumlah_hasil'].' / '.$pl['jumlah_belanja'].' / '.$pl['lebihan'].PHP_EOL;

$ok = $bs['total_aset'] === '183155.95' && $bs['seimbang'] && $pl['jumlah_hasil'] === '955006.47';
echo $ok ? "PEMASANGAN SEGAR: TALLY OK\n" : "PEMASANGAN SEGAR: GAGAL TALLY!\n";
exit($ok ? 0 : 1);

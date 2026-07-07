<?php
/**
 * Ujian HIDUP dual-write terhadap SPPKMS produksi (T2 langkah 3-5).
 * Sisi tempatan guna sppkms_test (DB produksi sppkms KEKAL bersih).
 * Cipta 3 rekod RM1 (kutipan/belanja/rekupmen) → hantar() ke SPPKMS →
 * lapor recno. Rekod SPPKMS dipadam kemudian melalui Playwright.
 *
 * Jalankan: php tools/dual_write_live_test.php <LOGIN> <PASSWORD>
 */
// CA bundle untuk PHP CLI tempatan (Windows XAMPP tiada curl.cainfo dikonfigurasi).
// Hanya untuk harness ujian ini — kod produksi SppkmsDualWriteService tidak diubah.
$ca = 'C:/Users/hakim/xampp/apache/bin/curl-ca-bundle.crt';
if (is_file($ca)) { ini_set('curl.cainfo', $ca); ini_set('openssl.cafile', $ca); }

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BankAccount;
use App\Services\Integration\SppkmsDualWriteService;
use App\Services\Security\SecretVaultService;
use App\Services\Transaksi\KutipanService;
use App\Services\Transaksi\PembayaranService;
use App\Support\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

// Paksa sisi tempatan ke sppkms_test (jangan sentuh sppkms produksi)
Config::set('database.connections.mariadb.database', 'sppkms_test');
DB::purge('mariadb');

$login = $argv[1] ?? null;
$pass  = $argv[2] ?? null;
if (!$login || !$pass) { fwrite(STDERR, "Guna: php tools/dual_write_live_test.php <LOGIN> <PASSWORD>\n"); exit(1); }

$mid = (int) config('spkm.masjid_id');
app()->instance('current.masjid_id', $mid);
app()->instance('current.user_id', 1);

$vault = app(SecretVaultService::class);
Setting::set('sppkms_login_ref', $vault->put($login), $mid);
Setting::set('sppkms_password_ref', $vault->put($pass), $mid);
Setting::set('dual_write_sppkms', 'on', $mid); // aktifkan supaya observer cipta baris sync

$coaId = fn ($kod) => (int) DB::table('coa')->where('masjid_id', $mid)->where('kod', $kod)->value('id');
$bank = BankAccount::withoutMasjidScope()->where('masjid_id', $mid)->firstOrFail();
$dw = app(SppkmsDualWriteService::class);
$ts = date('His');

function lapor($label, $sync) {
    echo str_pad($label, 12).': status='.$sync->status
        .' recno='.($sync->sppkms_recno ?? '-')
        .' endpoint='.($sync->sppkms_endpoint ?? '-')
        .($sync->last_error ? ' RALAT='.$sync->last_error : '')."\n";
}

$hasil = [];
try {
    // 1) KUTIPAN RM1 (Bank/QR) — uji bank=slot
    $k = app(KutipanService::class)->create([
        'tarikh' => date('Y-m-d'), 'coa_id' => $coaId('400-03010'),
        'kaedah' => 'BANK_TRANSFER_QR', 'jumlah' => '1.00',
        'no_resit' => 'DWLIVE-K-'.$ts, 'bank_account_id' => $bank->id,
        'nama_pemberi' => 'UJIAN DUAL-WRITE', 'no_slip' => 'DWTEST',
        'tar_bankin' => date('Y-m-d'), 'deskripsi' => 'UJIAN DUAL-WRITE V2 - SILA ABAIKAN/PADAM',
    ]);
    $sk = \App\Models\SppkmsSync::withoutMasjidScope()->where('source_type', 'KUTIPAN')->where('source_id', $k->id)->firstOrFail();
    $dw->hantar($sk); lapor('KUTIPAN', $sk->fresh()); $hasil['kutipan'] = $sk->fresh();

    // 2) BELANJA RM1 (EFT) — uji bank=kod COA
    $p = app(PembayaranService::class)->createBayaran([
        'tar_lulus' => date('Y-m-d'), 'coa_id' => $coaId('600-06000'),
        'jumlah' => '1.00', 'cara_bayar' => 'EFT', 'bank_account_id' => $bank->id,
        'baucer_no' => 'DWLIVE-PV-'.$ts, 'pemohon' => 'UJIAN DUAL-WRITE',
        'deskripsi' => 'UJIAN DUAL-WRITE V2 - SILA ABAIKAN/PADAM',
    ]);
    $sp = \App\Models\SppkmsSync::withoutMasjidScope()->where('source_type', 'BAYARAN')->where('source_id', $p->id)->firstOrFail();
    $dw->hantar($sp); lapor('BELANJA', $sp->fresh()); $hasil['belanja'] = $sp->fresh();

    // 3) REKUPMEN RM1 — uji pwr_coa & bank_coa = kod COA
    $r = app(PembayaranService::class)->createRekupmen([
        'tar_lulus' => date('Y-m-d'), 'pwr_coa_id' => $coaId('250-06010'),
        'bank_account_id' => $bank->id, 'jumlah' => '1.00',
        'baucer_no' => 'DWLIVE-RK-'.$ts, 'pemohon' => 'UJIAN DUAL-WRITE',
        'deskripsi' => 'UJIAN DUAL-WRITE V2 - SILA ABAIKAN/PADAM', 'cara_bayar' => 'EFT',
    ]);
    $sr = \App\Models\SppkmsSync::withoutMasjidScope()->where('source_type', 'BAYARAN')->where('source_id', $r->id)->firstOrFail();
    $dw->hantar($sr); lapor('REKUPMEN', $sr->fresh()); $hasil['rekupmen'] = $sr->fresh();

    echo "\nRINGKASAN recno SPPKMS (untuk verifikasi & pemadaman):\n";
    echo json_encode(array_map(fn ($s) => ['endpoint' => $s->sppkms_endpoint, 'recno' => $s->sppkms_recno, 'status' => $s->status], $hasil), JSON_PRETTY_PRINT)."\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "RALAT: ".$e->getMessage()."\n".$e->getTraceAsString()."\n");
    exit(1);
}

<?php
/** Diagnostik: POST rekupmen RM1 ke SPPKMS, dump status+Location+body utk cari
 *  penanda kejayaan. Rekod tercipta dipadam dalam fasa pembersihan. */
$ca = 'C:/Users/hakim/xampp/apache/bin/curl-ca-bundle.crt';
if (is_file($ca)) { ini_set('curl.cainfo', $ca); ini_set('openssl.cafile', $ca); }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

Config::set('database.connections.mariadb.database', 'sppkms_test');
DB::purge('mariadb');

$login = $argv[1]; $pass = $argv[2];
$base = rtrim(config('spkm.legacy_url'), '/').'/';

// Login
$r = Http::asForm()->withOptions(['allow_redirects' => false, 'verify' => config('dualwrite.verify', true)])
    ->post($base.'login-exec.php', ['login' => $login, 'password' => $pass, 'hp_field' => '']);
$sessid = null;
foreach ($r->toPsrResponse()->getHeader('Set-Cookie') as $h) {
    if (preg_match('/PHPSESSID=([^;,\s]+)/', $h, $m)) { $sessid = $m[1]; break; }
}
echo "login sessid=".($sessid ? 'ok' : 'GAGAL')."\n";

// POST rekupmen RM1
$ts = date('His');
$resp = Http::asForm()->withOptions(['allow_redirects' => false, 'verify' => config('dualwrite.verify', true)])
    ->withHeaders(['Cookie' => 'PHPSESSID='.$sessid])
    ->post($base.'pwr_rekupmen.php', [
        'tarmohon' => date('Y-m-d'), 'tarlulus' => date('Y-m-d'),
        'baucerno' => 'DWDIAG-RK-'.$ts, 'pwr_coa' => '250-06010', 'bank_coa' => '250-05010',
        'jumlah' => '1.00', 'pemohon' => 'UJIAN DUAL-WRITE V2 - SILA PADAM',
        'deskripsi' => 'UJIAN DUAL-WRITE V2 - SILA ABAIKAN/PADAM', 'carapembyrn' => '2', 'nocek' => '',
    ]);

echo "STATUS: ".$resp->status()."\n";
echo "LOCATION: [".$resp->header('Location')."]\n";
$body = $resp->body();
echo "BODY LEN: ".strlen($body)."\n";
// cari baris bermaklumat (berjaya/disimpan/ralat/baucer)
foreach (preg_split('/\n/', $body) as $ln) {
    if (preg_match('/berjaya|disimpan|success|ralat|error|gagal|DWDIAG|rekupmen|recno=|listingP/i', $ln)) {
        $t = trim(strip_tags($ln));
        if ($t !== '') echo "  > ".mb_substr($t, 0, 160)."\n";
    }
}
echo "baucerno DWDIAG dalam body? ".(str_contains($body, 'DWDIAG-RK-'.$ts) ? 'YA' : 'tidak')."\n";
echo "ada borang (name=baucerno)? ".(preg_match('/name=["\']baucerno["\']/i', $body) ? 'YA (re-render?)' : 'tidak')."\n";

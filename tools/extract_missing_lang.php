<?php
/**
 * Ekstrak semua kunci __('...') / @lang('...') dari blade (kecuali pdf/),
 * banding dengan lang/en.json, senaraikan kunci yang TIADA terjemahan EN.
 * Jalankan: php tools/extract_missing_lang.php
 */

$root = __DIR__.'/../resources/views';
$en = json_decode(file_get_contents(__DIR__.'/../lang/en.json'), true) ?: [];

$keys = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    if (!str_ends_with($f->getFilename(), '.blade.php')) continue;
    if (str_contains(str_replace('\\', '/', $f->getPathname()), '/views/pdf/')) continue; // pdf kekal BM
    $src = file_get_contents($f->getPathname());

    // __('...') atau __("...") — tangkap literal rentetan; abai yang ada pemboleh ubah ($)
    if (preg_match_all('/__\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*[\),]/', $src, $m)) {
        foreach ($m[1] as $k) $keys[stripcslashes($k)] = true;
    }
    if (preg_match_all('/__\(\s*"((?:[^"\\\\]|\\\\.)*)"\s*[\),]/', $src, $m)) {
        foreach ($m[1] as $k) $keys[stripcslashes($k)] = true;
    }
}

// Juga kunci dari config/sppkms.php (menu) — dirender melalui __(); baca sebagai teks
$cfg = file_get_contents(__DIR__.'/../config/sppkms.php');
if (preg_match_all("/'label'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'/", $cfg, $m)) {
    foreach ($m[1] as $k) $keys[stripcslashes($k)] = true;
}
if (preg_match_all("/\[\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'[a-z][a-z0-9_.]*'\s*\]/", $cfg, $m)) {
    foreach ($m[1] as $k) $keys[stripcslashes($k)] = true;
}

ksort($keys);
$missing = [];
foreach (array_keys($keys) as $k) {
    if ($k === '' || str_contains($k, '$')) continue; // langkau dinamik
    if (!array_key_exists($k, $en)) $missing[] = $k;
}

echo "Jumlah kunci __() unik: ".count($keys)."\n";
echo "Sudah ada EN: ".(count($keys) - count($missing))."\n";
echo "TIADA terjemahan EN: ".count($missing)."\n\n";
echo json_encode($missing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";

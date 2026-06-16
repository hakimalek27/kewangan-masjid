<?php
/**
 * Jana model Eloquent daripada skema DB sppkms (sekali sahaja, Fasa 0).
 * Jalankan: php tools/generate_models.php
 * Model sedia ada TIDAK ditulis ganti melainkan --force.
 */

$pdo = new PDO('mysql:host=127.0.0.1;dbname=sppkms;charset=utf8mb4', 'root', '');
$force = in_array('--force', $argv);

$skip = [
    'migrations', 'users', 'password_reset_tokens', 'sessions',
    'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'secret_vault',
];

$tables = $pdo->query(
    "SELECT table_name FROM information_schema.tables
     WHERE table_schema='sppkms' AND table_type='BASE TABLE'"
)->fetchAll(PDO::FETCH_COLUMN);

$made = [];
foreach ($tables as $table) {
    if (in_array($table, $skip)) continue;

    $cols = $pdo->query(
        "SELECT column_name, data_type, column_key, extra, is_nullable
         FROM information_schema.columns
         WHERE table_schema='sppkms' AND table_name=" . $pdo->quote($table) . "
         ORDER BY ordinal_position"
    )->fetchAll(PDO::FETCH_ASSOC);

    $colNames   = array_column($cols, 'column_name');
    $class      = str_replace(' ', '', ucwords(str_replace('_', ' ', $table)));
    $path       = __DIR__ . '/../app/Models/' . $class . '.php';
    if (file_exists($path) && !$force) { echo "skip  $class (wujud)\n"; continue; }

    $pks = array_values(array_filter($cols, fn($c) => $c['column_key'] === 'PRI'));
    $autoInc = count(array_filter($cols, fn($c) => str_contains($c['extra'], 'auto_increment'))) > 0;

    $lines = [];
    $lines[] = "    protected \$table = '$table';";
    if (count($pks) === 1 && $pks[0]['column_name'] !== 'id') {
        $lines[] = "    protected \$primaryKey = '{$pks[0]['column_name']}';";
    }
    if (count($pks) > 1) {
        $lines[] = "    // PK komposit (" . implode(', ', array_column($pks, 'column_name')) . ") — guna query builder untuk kemas kini";
        $lines[] = "    protected \$primaryKey = '{$pks[0]['column_name']}';";
    }
    if (!$autoInc) $lines[] = "    public \$incrementing = false;";
    if (!$autoInc && count($pks) === 1 && !in_array($pks[0]['data_type'], ['int','bigint','smallint','tinyint','mediumint'])) {
        $lines[] = "    protected \$keyType = 'string';";
    }

    $hasCreated = in_array('created_at', $colNames);
    $hasUpdated = in_array('updated_at', $colNames);
    if (!$hasCreated && !$hasUpdated) {
        $lines[] = '    public $timestamps = false;';
    } elseif ($hasCreated && !$hasUpdated) {
        $lines[] = '    const UPDATED_AT = null;';
    } elseif (!$hasCreated && $hasUpdated) {
        $lines[] = '    const CREATED_AT = null;';
    }

    $lines[] = '    protected $guarded = [];';

    $casts = [];
    foreach ($cols as $c) {
        $n = $c['column_name']; $t = $c['data_type'];
        if ($t === 'decimal')                    $casts[$n] = 'decimal:2';
        elseif ($t === 'date')                   $casts[$n] = 'date:Y-m-d';
        elseif (in_array($t, ['datetime','timestamp']) && !in_array($n, ['created_at','updated_at'])) $casts[$n] = 'datetime';
        elseif ($t === 'json' || $t === 'longtext' && str_ends_with($n, '_json')) $casts[$n] = 'array';
        elseif ($t === 'tinyint' && (str_starts_with($n, 'is_') || in_array($n, ['aktif','digunakan','posted','resolved','alerted','success','ok','encrypt','semak','auto_resit','allow_deficit','supports_vision','locked']))) $casts[$n] = 'boolean';
    }
    if ($casts) {
        $lines[] = '    protected $casts = [';
        foreach ($casts as $k => $v) $lines[] = "        '$k' => '$v',";
        $lines[] = '    ];';
    }

    $useMasjid = in_array('masjid_id', $colNames);
    $useTrait  = $useMasjid ? "    use \\App\\Models\\Concerns\\BelongsToMasjid;\n\n" : '';

    $php = "<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass $class extends Model\n{\n$useTrait" . implode("\n", $lines) . "\n}\n";
    file_put_contents($path, $php);
    $made[] = $class;
    echo "jana  $class\n";
}
echo "\nSelesai: " . count($made) . " model dijana.\n";

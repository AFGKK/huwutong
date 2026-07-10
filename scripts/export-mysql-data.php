<?php

/**
 * Windows / phpEnv 下导出 MySQL 数据（供离线包使用，遗留栈）
 *
 * 用法: php scripts/export-mysql-data.php [输出目录]
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$outDir = $argv[1] ?? __DIR__.'/../deploy/air-gapped/data/mysql';
if (! is_dir($outDir) && ! mkdir($outDir, 0755, true)) {
    fwrite(STDERR, "无法创建目录: {$outDir}\n");
    exit(1);
}

$host = env('DB_HOST', '127.0.0.1');
$port = env('DB_PORT', '3306');
$database = env('DB_DATABASE', 'huwutong');
$user = env('DB_USERNAME', 'root');
$password = env('DB_PASSWORD', '');
$dumpFile = rtrim($outDir, '/\\').DIRECTORY_SEPARATOR.'huwutong.sql.gz';

$mysqldump = getenv('MYSQLDUMP_PATH') ?: 'mysqldump';
$cmd = sprintf(
    '%s --host=%s --port=%s --user=%s --password=%s --single-transaction --quick --skip-lock-tables --routines --events --add-drop-table --set-gtid-purged=OFF %s',
    escapeshellarg($mysqldump),
    escapeshellarg($host),
    escapeshellarg((string) $port),
    escapeshellarg($user),
    escapeshellarg($password),
    escapeshellarg($database)
);

$pipe = popen($cmd.' 2>&1', 'r');
if ($pipe === false) {
    fwrite(STDERR, "无法执行 mysqldump\n");
    exit(1);
}

$gz = gzopen($dumpFile, 'wb9');
if ($gz === false) {
    fwrite(STDERR, "无法写入: {$dumpFile}\n");
    exit(1);
}

while (! feof($pipe)) {
    $chunk = fread($pipe, 65536);
    if ($chunk === false) {
        break;
    }
    gzwrite($gz, $chunk);
}
$exitCode = pclose($pipe);
gzclose($gz);

if ($exitCode !== 0 || ! file_exists($dumpFile) || filesize($dumpFile) < 100) {
    @unlink($dumpFile);
    fwrite(STDERR, "mysqldump 失败 (exit {$exitCode})\n");
    exit(1);
}

$meta = [
    'dumped_at' => gmdate('c'),
    'engine' => 'mysql',
    'source_host' => $host,
    'database' => $database,
    'username' => $user,
    'format' => 'sql.gz',
    'file_size' => round(filesize($dumpFile) / 1024 / 1024, 2).' MB',
    'git_commit' => trim((string) shell_exec('git rev-parse HEAD 2>nul') ?: 'n/a'),
];

file_put_contents(
    rtrim($outDir, '/\\').DIRECTORY_SEPARATOR.'manifest.txt',
    implode("\n", array_map(fn ($k, $v) => "{$k}={$v}", array_keys($meta), $meta))."\n"
);

echo "✅ 导出完成: {$dumpFile} ({$meta['file_size']})\n";

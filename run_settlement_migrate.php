<?php
// Check migration status
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
$kernel->bootstrap();

// Check migrations table
$migrations = $app['db']->table('migrations')
    ->where('migration', 'like', '%2026_06_27%')
    ->get();

echo "P3 Migrations:\n";
foreach ($migrations as $m) {
    echo "  - {$m->migration} (batch {$m->batch})\n";
}

// Try force running the settlement migration
echo "\nAttempting to run settlement migration...\n";

$exitCode = \Artisan::call('migrate', [
    '--path' => 'database/migrations/2026_06_27_000002_create_settlement_tables.php',
    '--force' => true,
]);
echo "Exit code: $exitCode\n";
echo \Artisan::output();

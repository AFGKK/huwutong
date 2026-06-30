<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
$kernel->bootstrap();

$schema = $app['db']->getSchemaBuilder();

echo "=== Tables Check ===\n";
$tables = ['settlement_cycles', 'settlement_batches', 'settlement_batch_items', 'platform_fees'];
foreach ($tables as $table) {
    echo "$table: " . ($schema->hasTable($table) ? 'EXISTS' : 'MISSING') . "\n";
}

echo "\n=== commission_settlements columns ===\n";
$cols = ['settlement_batch_id', 'settlement_cycle_id', 'fee', 'net_amount', 'payout_method'];
foreach ($cols as $col) {
    echo "$col: " . ($schema->hasColumn('commission_settlements', $col) ? 'EXISTS' : 'MISSING') . "\n";
}

echo "\n=== earnings_accounts columns ===\n";
$cols2 = ['last_settlement_at', 'next_settlement_at', 'lifetime_settled'];
foreach ($cols2 as $col) {
    echo "$col: " . ($schema->hasColumn('earnings_accounts', $col) ? 'EXISTS' : 'MISSING') . "\n";
}

echo "\n=== Testing Dashboard ===\n";
try {
    $svc = $app->make(\App\Services\SettlementService::class);
    $data = $svc->getDashboard(1);
    echo "SUCCESS\n";
    echo "pending_settlements: " . ($data['pending_settlements'] ?? 'N/A') . "\n";
    echo "releasable_count: " . ($data['releasable_count'] ?? 'N/A') . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}

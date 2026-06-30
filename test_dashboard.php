<?php
// Clear opcache and test the dashboard
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared\n";
}

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
$kernel->bootstrap();

// Clear config & route cache
$app['cache']->forget('spatie.permission.cache');

echo "Testing SettlementService::getDashboard()...\n";
try {
    // Check tables first
    $schema = $app['db']->getSchemaBuilder();
    echo "settlement_cycles: " . ($schema->hasTable('settlement_cycles') ? 'YES' : 'NO') . "\n";
    echo "settlement_batches: " . ($schema->hasTable('settlement_batches') ? 'YES' : 'NO') . "\n";
    echo "commission_settlements: " . ($schema->hasTable('commission_settlements') ? 'YES' : 'NO') . "\n";
    echo "platform_fees: " . ($schema->hasTable('platform_fees') ? 'YES' : 'NO') . "\n";
    
    $svc = $app->make(\App\Services\SettlementService::class);
    $data = $svc->getDashboard(1);
    echo "SUCCESS\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Class: " . get_class($e) . "\n";
}

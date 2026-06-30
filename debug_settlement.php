<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
$kernel->bootstrap();

try {
    $svc = $app->make(\App\Services\SettlementService::class);
    $data = $svc->getDashboard(1);
    echo "getDashboard() OK\n";
    print_r(array_keys($data));
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

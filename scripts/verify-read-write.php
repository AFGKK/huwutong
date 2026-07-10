<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\DatabaseReadWriteService;

$svc = app(DatabaseReadWriteService::class);
$status = $svc->getStatus();

echo "=== 读写分离状态 ===\n";
echo 'enabled: '.($status['enabled'] ? 'true' : 'false')."\n";
echo 'master: '.$status['master_connection']."\n";
echo 'replica: '.$status['replica_connection']."\n";
echo 'replica_healthy: '.($status['replica_healthy'] ? 'true' : 'false')."\n";

$health = $svc->checkReplicaHealth();
echo 'health_check: '.json_encode($health, JSON_UNESCAPED_UNICODE)."\n";

$conn = $svc->getConnection('read');
echo "getConnection(read): {$conn}\n";
echo ($conn === config('database.default') || $conn === 'pgsql_replica' ? "✅ 连接名正确\n" : "⚠️ 意外连接名\n");

<?php

/**
 * PG 迁移后全量同步 Meilisearch 索引
 *
 * 用法: php scripts/sync-meilisearch.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MeilisearchService;

$svc = app(MeilisearchService::class);

if (! $svc->isAvailable()) {
    echo "❌ Meilisearch 不可用，请检查 MEILISEARCH_HOST / 服务是否启动\n";
    echo "   Windows: powershell -File scripts/start-meilisearch.ps1\n";
    echo "   Docker:  docker compose -f deploy/meilisearch/docker-compose.yml up -d\n";
    exit(1);
}

echo "=== Meilisearch 全量同步 ===\n\n";

echo "初始化索引...\n";
foreach ($svc->setupAllIndexes() as $indexKey => $result) {
    $status = $result['status'] ?? 'ok';
    echo "  ✅ {$indexKey}: {$status}\n";
}
echo "\n";

$results = $svc->syncAll(false);

foreach ($results as $index => $stats) {
    $synced = $stats['synced'] ?? 0;
    $status = '✅';
    echo "{$status} {$index}: synced={$synced}\n";
}

echo "\n完成.\n";

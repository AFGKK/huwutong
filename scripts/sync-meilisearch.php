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
    echo "   docker compose -f deploy/docker/docker-compose.pgsql.yml up -d meilisearch\n";
    exit(1);
}

echo "=== Meilisearch 全量同步 ===\n\n";

$results = $svc->syncAll();

foreach ($results as $index => $stats) {
    $synced = $stats['synced'] ?? 0;
    $status = '✅';
    echo "{$status} {$index}: synced={$synced}\n";
}

echo "\n完成.\n";

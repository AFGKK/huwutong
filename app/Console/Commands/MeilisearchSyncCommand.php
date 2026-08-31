<?php

namespace App\Console\Commands;

use App\Services\MeilisearchService;
use Illuminate\Console\Command;

/**
 * D-34: Meilisearch 全量/分索引同步
 *
 * php artisan meilisearch:sync
 * php artisan meilisearch:sync --type=products
 * php artisan meilisearch:sync --setup-only
 */
class MeilisearchSyncCommand extends Command
{
    protected $signature = 'meilisearch:sync
        {--type=all : 索引类型 products|kb_articles|...|all}
        {--no-setup : 跳过同步前的索引初始化}
        {--setup-only : 仅初始化索引，不同步数据}
        {--rebuild : 删除并重建全部索引后再同步（D-19）}';

    protected $description = '同步数据到 Meilisearch（D-34）';

    public function handle(MeilisearchService $service): int
    {
        $this->info('=== Meilisearch 同步 ===');

        if (! $service->isAvailable()) {
            $host = config('meilisearch.host');
            $this->error("Meilisearch 不可用 ({$host})");
            $this->line('  Windows: powershell -ExecutionPolicy Bypass -File scripts/start-meilisearch.ps1');
            $this->line('  Docker:  docker compose -f deploy/meilisearch/docker-compose.yml up -d');
            $this->line('  重建:    php artisan meilisearch:sync --rebuild');

            return self::FAILURE;
        }

        if ($this->option('rebuild')) {
            $this->warn('正在删除并重建全部索引...');
            $rebuild = $service->rebuildAllIndexes();
            foreach ($rebuild['setup'] ?? [] as $indexKey => $result) {
                $status = $result['status'] ?? 'ok';
                $this->line("  ♻️ {$indexKey}: {$status}");
            }
            $this->line('');
        }

        $health = $service->getHealth();
        $version = is_array($health['version'] ?? null)
            ? ($health['version']['pkgVersion'] ?? 'unknown')
            : ($health['version'] ?? 'unknown');
        $this->line("服务状态: {$health['status']} | 版本: {$version}");
        $this->line('');

        $setupOnly = (bool) $this->option('setup-only');
        $withSetup = ! $this->option('no-setup');

        if ($withSetup || $setupOnly) {
            $this->info('初始化索引...');
            foreach ($service->setupAllIndexes() as $indexKey => $result) {
                $status = $result['status'] ?? 'ok';
                $this->line("  ✅ {$indexKey}: {$status}");
            }
            $this->line('');
        }

        if ($setupOnly) {
            $this->info('完成（仅初始化索引）');

            return self::SUCCESS;
        }

        $type = $this->option('type') ?? 'all';
        $this->info($type === 'all' ? '全量同步...' : "同步 {$type}...");

        try {
            $results = $type === 'all'
                ? $service->syncAll(false)
                : [$type => $this->syncSingle($service, $type)];
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $total = 0;
        foreach ($results as $index => $stats) {
            $synced = $stats['synced'] ?? 0;
            $total += $synced;
            $this->line("  ✅ {$index}: synced={$synced}");
        }

        $this->line('');
        $this->info("完成，共同步 {$total} 条记录");

        return self::SUCCESS;
    }

    private function syncSingle(MeilisearchService $service, string $type): array
    {
        return match ($type) {
            'products' => $service->syncProducts(),
            'kb_articles' => $service->syncKbArticles(),
            'marketplace_apps' => $service->syncMarketplaceApps(),
            'forum_posts' => $service->syncForumPosts(),
            'blog_posts' => $service->syncBlogPosts(),
            'oa_articles' => $service->syncOaArticles(),
            'users' => $service->syncUsers(),
            'official_accounts' => $service->syncOfficialAccounts(),
            default => throw new \RuntimeException(__('app.common.unknown_index_type', ['type' => $type])),
        };
    }
}

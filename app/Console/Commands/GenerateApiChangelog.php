<?php

namespace App\Console\Commands;

use App\Models\ApiVersion;
use App\Services\ApiDocsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateApiChangelog extends Command
{
    protected $signature = 'api-docs:auto-changelog
                            {--api-version-id= : 指定 API 版本 ID，不指定则处理所有活跃版本}
                            {--notify : 在控制台输出变更摘要}';

    protected $description = 'M3-32 自动检测 API 端点变更并生成 Changelog';

    public function handle(ApiDocsService $apiDocsService): int
    {
        $versionId = $this->option('api-version-id');

        if ($versionId) {
            $versions = [ApiVersion::findOrFail($versionId)];
        } else {
            $versions = ApiVersion::whereIn('status', ['active', 'deprecated'])->get();
        }

        if ($versions->isEmpty()) {
            $this->warn('没有找到活跃或已废弃的 API 版本');
            return 0;
        }

        $totalChanges = 0;

        foreach ($versions as $version) {
            $this->info("处理 API 版本: {$version->version} (ID: {$version->id})");

            try {
                $result = $apiDocsService->autoGenerateChangelog($version->id);

                $changes = $result['changes'] ?? [];
                $count = $result['changelogs_created'] ?? 0;
                $totalChanges += $count;

                if ($result['status'] === 'snapshot_created') {
                    $this->info("  首个快照已创建，共 {$result['message']}");
                    continue;
                }

                $this->line("  新增: {$changes['added_count']}, 变更: {$changes['changed_count']}, 废弃: {$changes['deprecated_count']}, 删除: {$changes['removed_count']}, 恢复: {$changes['reactivated_count']}");

                if ($count > 0) {
                    $this->info("  已生成 {$count} 条 Changelog 记录");
                } else {
                    $this->line('  无变更');
                }

                Log::info('API Changelog auto-generated', [
                    'api_version_id' => $version->id,
                    'version' => $version->version,
                    'changelogs_created' => $count,
                    'changes' => $changes,
                ]);
            } catch (\Exception $e) {
                $this->error("处理版本 {$version->version} 失败: {$e->getMessage()}");
                Log::error('API Changelog auto-generation failed', [
                    'api_version_id' => $version->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("完成: 共生成 {$totalChanges} 条 Changelog 记录");
        return 0;
    }
}

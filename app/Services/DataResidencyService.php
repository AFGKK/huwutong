<?php

namespace App\Services;

use App\Models\DataMigration;
use App\Models\DataResidencyRecord;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 数据本地化存储服务 (M3-60)
 *
 * 按租户/区域指定数据存储位置
 * - 区域配置管理
 * - 自动路由
 * - 数据迁移
 * - 合规审计
 */
class DataResidencyService
{
    /**
     * 缓存键
     */
    const CACHE_KEY_REGIONS = 'data_residency:regions';

    /**
     * 获取所有可用区域
     */
    public function getRegions(): array
    {
        return Cache::remember(self::CACHE_KEY_REGIONS, 3600, function () {
            return config('data-residency.regions', []);
        });
    }

    /**
     * 获取区域详情
     */
    public function getRegion(string $code): ?array
    {
        $regions = $this->getRegions();
        return $regions[$code] ?? null;
    }

    /**
     * 获取仪表盘数据
     */
    public function getDashboard(): array
    {
        $regions = $this->getRegions();
        $records = DataResidencyRecord::with('tenant')->get();
        $migrations = DataMigration::orderBy('created_at', 'desc')->limit(10)->get();

        $regionStats = [];
        foreach ($regions as $code => $config) {
            $regionRecords = $records->where('region_code', $code);
            $regionStats[$code] = [
                'name' => $config['name'],
                'tenant_count' => Tenant::where('data_region', $code)->count(),
                'record_count' => $regionRecords->count(),
                'active_count' => $regionRecords->where('status', 'active')->count(),
                'compliance' => $config['compliance'],
            ];
        }

        return [
            'regions' => $regionStats,
            'total_regions' => count($regions),
            'total_tenants_with_region' => Tenant::whereNotNull('data_region')->count(),
            'total_records' => $records->count(),
            'recent_migrations' => $migrations,
            'default_region' => collect($regions)->firstWhere('default', true)['name'] ?? 'us-east',
        ];
    }

    /**
     * 为租户分配区域
     */
    public function assignTenantRegion(int $tenantId, string $region): Tenant
    {
        $tenant = Tenant::findOrFail($tenantId);
        $regions = $this->getRegions();

        if (!isset($regions[$region])) {
            throw new \InvalidArgumentException(__("app.data_residency.msg_d59130c1"));
        }

        $tenant->update(['data_region' => $region]);

        Log::info('DataResidency: Tenant region assigned', [
            'tenant_id' => $tenantId,
            'region' => $region,
        ]);

        return $tenant->fresh();
    }

    /**
     * 为数据分类创建区域绑定
     */
    public function createResidencyRecord(int $tenantId, string $regionCode, string $classification): DataResidencyRecord
    {
        $region = $this->getRegion($regionCode);
        if (!$region) {
            throw new \InvalidArgumentException(__("app.data_residency.msg_90988a9e"));
        }

        $classifications = config('data-residency.data_classifications', []);
        $classConfig = $classifications[$classification] ?? [];

        return DataResidencyRecord::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'data_classification' => $classification,
            ],
            [
                'region_code' => $regionCode,
                'storage_driver' => $region['storage'] ?? 's3',
                'bucket' => $region['bucket'] ?? '',
                'cdn_domain' => $region['cdn_domain'] ?? '',
                'encryption_enabled' => $classConfig['encrypt'] ?? true,
                'retention_days' => $classConfig['retention_days'] ?? 365,
                'status' => 'active',
            ]
        );
    }

    /**
     * 获取数据存储目标 (自动路由)
     *
     * 根据租户区域 + 数据分类决定存储位置
     */
    public function resolveStorageTarget(int $tenantId, string $dataClassification): array
    {
        // 1. 查找精准匹配的记录
        $record = DataResidencyRecord::where('tenant_id', $tenantId)
            ->where('data_classification', $dataClassification)
            ->where('status', 'active')
            ->first();

        if ($record) {
            return [
                'driver' => $record->storage_driver,
                'bucket' => $record->bucket,
                'region' => $record->region_code,
                'encrypt' => $record->encryption_enabled,
                'source' => 'tenant_record',
            ];
        }

        // 2. 按租户区域 + 数据分类默认区域
        $tenant = Tenant::find($tenantId);
        $tenantRegion = $tenant?->data_region;
        $classifications = config('data-residency.data_classifications', []);
        $classConfig = $classifications[$dataClassification] ?? [];
        $classRegion = $classConfig['region'] ?? null;

        $targetRegion = $classRegion ?: $tenantRegion;

        if ($targetRegion) {
            $region = $this->getRegion($targetRegion);
            if ($region) {
                return [
                    'driver' => $region['storage'],
                    'bucket' => $region['bucket'],
                    'region' => $targetRegion,
                    'encrypt' => $classConfig['encrypt'] ?? true,
                    'source' => 'auto_routing',
                ];
            }
        }

        // 3. 兜底到默认区域
        $fallback = config('data-residency.auto_routing.fallback_region', 'us-east');
        $region = $this->getRegion($fallback);

        return [
            'driver' => $region['storage'] ?? 's3',
            'bucket' => $region['bucket'] ?? '',
            'region' => $fallback,
            'encrypt' => true,
            'source' => 'fallback',
        ];
    }

    /**
     * 启动数据迁移
     */
    public function startMigration(int $tenantId, string $sourceRegion, string $targetRegion, string $classification): DataMigration
    {
        $migration = DataMigration::create([
            'tenant_id' => $tenantId,
            'source_region' => $sourceRegion,
            'target_region' => $targetRegion,
            'data_classification' => $classification,
            'status' => 'pending',
            'performed_by' => auth()->user()?->name ?? 'system',
        ]);

        Log::info('DataResidency: Migration started', [
            'tenant_id' => $tenantId,
            'from' => $sourceRegion,
            'to' => $targetRegion,
            'classification' => $classification,
        ]);

        return $migration;
    }

    /**
     * 执行迁移 (后台)
     */
    public function executeMigration(int $migrationId): DataMigration
    {
        $migration = DataMigration::findOrFail($migrationId);
        $migration->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $tenantId = $migration->tenant_id;
            $bucket = config("data-residency.regions.{$migration->source_region}.bucket");
            $targetDriver = config("data-residency.regions.{$migration->target_region}.storage");

            // 模拟文件迁移 — 实际会遍历文件并跨区域复制
            $sourceDisk = Storage::disk($bucket ?? 's3');
            $files = $sourceDisk->files("tenants/{$tenantId}/");

            $total = count($files);
            $processed = 0;
            $failed = 0;

            foreach ($files as $file) {
                try {
                    $contents = $sourceDisk->get($file);
                    $targetPath = str_replace("tenants/{$tenantId}/", "tenants/{$tenantId}/", $file);

                    // 写入目标区域存储
                    Storage::disk($targetDriver)->put($targetPath, $contents);

                    $processed++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::error('DataResidency: File migration failed', [
                        'file' => $file,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 更新区域记录
            $this->createResidencyRecord($tenantId, $migration->target_region, $migration->data_classification);

            $migration->update([
                'status' => 'completed',
                'completed_at' => now(),
                'total_items' => $total,
                'processed_items' => $processed,
                'failed_items' => $failed,
                'audit_log' => [
                    'completed_at' => now()->toIso8601String(),
                    'source_region' => $migration->source_region,
                    'target_region' => $migration->target_region,
                    'files_migrated' => $processed,
                    'files_failed' => $failed,
                    'performed_by' => $migration->performed_by,
                ],
            ]);

            Log::info('DataResidency: Migration completed', [
                'migration_id' => $migrationId,
                'processed' => $processed,
                'failed' => $failed,
            ]);
        } catch (\Throwable $e) {
            $migration->update([
                'status' => 'failed',
                'completed_at' => now(),
                'audit_log' => [['error' => $e->getMessage()]],
            ]);
        }

        return $migration->fresh();
    }

    /**
     * 获取合规审计数据
     */
    public function getComplianceAudit(): array
    {
        $records = DataResidencyRecord::with('tenant')->get();
        $migrations = DataMigration::orderBy('created_at', 'desc')->limit(50)->get();

        $byRegion = $records->groupBy('region_code')->map(fn($g, $code) => [
            'region' => $code,
            'count' => $g->count(),
            'tenants' => $g->pluck('tenant.name')->unique()->values(),
        ]);

        return [
            'total_bindings' => $records->count(),
            'total_migrations' => $migrations->count(),
            'completed_migrations' => $migrations->where('status', 'completed')->count(),
            'records_by_region' => $byRegion,
            'recent_migrations' => $migrations->take(10),
        ];
    }

    /**
     * 获取数据分类列表
     */
    public function getDataClassifications(): array
    {
        return config('data-residency.data_classifications', []);
    }
}

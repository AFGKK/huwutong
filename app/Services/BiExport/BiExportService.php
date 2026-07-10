<?php

namespace App\Services\BiExport;

use App\Models\BiConnection;
use App\Models\BiDataset;
use App\Models\BiSyncLog;
use Illuminate\Support\Facades\Log;

/**
 * BI 数据导出引擎
 * 
 * 专注于 CSV 文件导出，满足大多数 BI 工具的原始数据需求
 */
class BiExportService
{
    /**
     * 测试连接
     */
    public function testConnection(BiConnection $connection): bool
    {
        try {
            $connector = $this->getConnector($connection);
            $result = $connector->test();
            $connection->update([
                'status' => $result ? 'connected' : 'error',
                'last_error' => $result ? null : 'Connection test failed',
            ]);
            return $result;
        } catch (\Exception $e) {
            $connection->update(['status' => 'error', 'last_error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 同步数据集
     */
    public function syncDataset(BiDataset $dataset): BiSyncLog
    {
        $log = BiSyncLog::create([
            'bi_dataset_id' => $dataset->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $connection = $dataset->connection;
            $connector = $this->getConnector($connection);

            $data = $this->fetchSourceData($dataset);
            $transformed = $this->transformData($data, $dataset->field_mapping ?? []);
            $result = $connector->export($dataset->name, $transformed);

            $log->update([
                'status' => $result['success'] ? 'success' : 'partial',
                'total_records' => count($data),
                'synced_records' => $result['count'] ?? 0,
                'error_message' => $result['error'] ?? null,
                'completed_at' => now(),
            ]);

            $dataset->update(['last_synced_at' => now()]);
            $connection->update(['last_sync_at' => now(), 'last_success_at' => now()]);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            Log::error('BI sync failed', ['dataset' => $dataset->id, 'error' => $e->getMessage()]);
        }

        return $log->fresh();
    }

    /**
     * 获取对应平台的连接器
     */
    public function getConnector(BiConnection $connection): BiConnectorInterface
    {
        $config = $connection->config ?? [];
        // 简化：仅支持本地数据聚合导出 (CSV)
        // 原多平台连接器已移除
        throw new \InvalidArgumentException("BI platform connectors removed. Use CSV export instead.");
    }

    /**
     * 从本地数据库获取源数据
     */
    protected function fetchSourceData(BiDataset $dataset): array
    {
        $source = $dataset->source_table;
        $filters = $dataset->filters ?? [];

        $modelClass = match ($source) {
            'licenses'      => \App\Models\License::class,
            'customers'     => \App\Models\Customer::class,
            'orders'        => \App\Models\Order::class,
            'invoices'      => \App\Models\Invoice::class,
            'subscriptions' => \App\Models\Subscription::class,
            default => throw new \InvalidArgumentException("Unknown source table: {$source}"),
        };

        $query = $modelClass::where('tenant_id', $dataset->tenant_id)
            ->when(!empty($filters['start_date']), fn($q) => $q->where('created_at', '>=', $filters['start_date']))
            ->when(!empty($filters['end_date']), fn($q) => $q->where('created_at', '<=', $filters['end_date']))
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->limit(10000);

        return $query->get()->toArray();
    }

    /**
     * 根据字段映射转换数据
     */
    protected function transformData(array $data, array $mapping): array
    {
        if (empty($mapping)) return $data;

        return array_map(function ($row) use ($mapping) {
            $transformed = [];
            foreach ($mapping as $localField => $targetField) {
                $transformed[$targetField] = $row[$localField] ?? null;
            }
            return $transformed;
        }, $data);
    }

    /**
     * 获取平台配置模板（用于前端表单）
     */
    public static function getPlatformConfigTemplate(string $platform): array
    {
        // 简化：仅支持 CSV 导出
        return [];
    }
}
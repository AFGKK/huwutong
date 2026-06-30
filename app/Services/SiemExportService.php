<?php

namespace App\Services;

use App\Models\Log;
use App\Models\SiemConnection;
use App\Models\SiemPushLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log as LogFacade;

/**
 * SIEM 审计日志导出服务 (M2-52)
 *
 * 支持 Splunk CEF / ELK JSON / 阿里云 SLS 格式转换与推送
 */
class SiemExportService
{
    /**
     * 获取连接列表
     */
    public function getConnections(int $tenantId): array
    {
        return SiemConnection::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * 创建/更新连接
     */
    public function saveConnection(int $tenantId, array $data, ?int $editId = null): SiemConnection
    {
        $data['tenant_id'] = $tenantId;

        if ($editId) {
            $connection = SiemConnection::findOrFail($editId);
            $connection->update($data);
            return $connection->fresh();
        }

        return SiemConnection::create($data);
    }

    /**
     * 删除连接
     */
    public function deleteConnection(int $id): bool
    {
        return SiemConnection::findOrFail($id)->delete() ? true : false;
    }

    /**
     * 测试连接
     */
    public function testConnection(int $connectionId): array
    {
        $connection = SiemConnection::findOrFail($connectionId);

        $testPayload = $this->convertFormat([[
            'id' => 0,
            'event_type' => 'test.connection',
            'tenant_id' => $connection->tenant_id,
            'user_id' => 0,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'SiemExportTest/1.0',
            'description' => 'SIEM 连接测试消息',
            'created_at' => now()->toIso8601String(),
            'severity' => 'info',
            'metadata' => ['test' => true],
        ]], $connection->format, $connection->field_mappings);

        try {
            $start = microtime(true);
            $response = Http::timeout(config('siem-export.push_timeout', 30))
                ->withHeaders($this->buildAuthHeaders($connection))
                ->post($connection->endpoint_url, $testPayload);
            $duration = (microtime(true) - $start) * 1000;

            $success = $response->successful();
            $this->logPush($connection->id, $success ? 'success' : 'failed', 1,
                $response->status(), $response->body(), null, $duration);

            return [
                'success' => $success,
                'status_code' => $response->status(),
                'duration_ms' => round($duration, 2),
                'message' => $success ? '连接测试成功' : "HTTP {$response->status()}",
            ];
        } catch (\Exception $e) {
            $duration = (microtime(true) - ($start ?? microtime(true))) * 1000;
            $this->logPush($connection->id, 'failed', 1, null, null, $e->getMessage(), $duration);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'duration_ms' => round($duration, 2),
            ];
        }
    }

    /**
     * 推送审计日志到 SIEM
     */
    public function pushLogs(int $connectionId, array $filters = []): array
    {
        $connection = SiemConnection::findOrFail($connectionId);

        // 合并连接级别的过滤器
        $queryFilters = array_merge(
            $connection->filters ?? [],
            $filters
        );

        $logs = $this->fetchAuditLogs($connection->tenant_id, $queryFilters, $connection->max_batch_size);
        if (empty($logs)) {
            return ['pushed' => 0, 'message' => '没有待推送的日志'];
        }

        $payload = $this->convertFormat($logs, $connection->format, $connection->field_mappings);

        try {
            $start = microtime(true);
            $response = Http::timeout(config('siem-export.push_timeout', 30))
                ->withHeaders($this->buildAuthHeaders($connection))
                ->post($connection->endpoint_url, $payload);
            $duration = (microtime(true) - $start) * 1000;

            $success = $response->successful();
            $status = $success ? 'success' : 'failed';
            $this->logPush($connection->id, $status, count($logs),
                $response->status(), $response->body(), null, $duration);

            if ($success) {
                $connection->update([
                    'last_push_at' => now(),
                    'last_success_at' => now(),
                ]);
            } else {
                $connection->update(['last_push_at' => now()]);
            }

            return [
                'pushed' => count($logs),
                'success' => $success,
                'status_code' => $response->status(),
                'duration_ms' => round($duration, 2),
            ];
        } catch (\Exception $e) {
            $duration = (microtime(true) - ($start ?? microtime(true))) * 1000;
            $this->logPush($connection->id, 'failed', count($logs), null, null, $e->getMessage(), $duration);
            $connection->update(['last_push_at' => now()]);

            return [
                'pushed' => 0,
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 日志格式转换 (核心方法)
     */
    public function convertFormat(array $logs, string $format, ?array $customMappings = null): array
    {
        $defaultMappings = config("siem-export.field_mappings.{$format}", []);

        return match ($format) {
            'cef' => $this->toCef($logs, $defaultMappings, $customMappings),
            'elk_json' => $this->toElkJson($logs, $defaultMappings, $customMappings),
            'sls' => $this->toSls($logs, $defaultMappings, $customMappings),
            default => $logs,
        };
    }

    /**
     * 转换为 Splunk CEF 格式
     */
    protected function toCef(array $logs, array $defaultMapping, ?array $customMapping): array
    {
        $mapping = $customMapping ?? $defaultMapping;
        $cefConfig = config('siem-export.cef');

        return array_map(function ($log) use ($mapping, $cefConfig) {
            $ext = [];
            foreach ($mapping as $field => $cefField) {
                $val = $log[$field] ?? '';
                if (is_array($val)) $val = json_encode($val);
                $ext[$cefField] = $val;
            }

            $severity = $cefConfig['severity_map'][$log['severity'] ?? 'info'] ?? 3;
            $extStr = implode(' ', array_map(fn($k, $v) => "{$k}={$v}", array_keys($ext), $ext));

            return "CEF:0|{$cefConfig['vendor']}|{$cefConfig['product']}|{$cefConfig['version']}|{$cefConfig['device_event_class_id']}|{$log['event_type']}|{$severity}|{$extStr}";
        }, $logs);
    }

    /**
     * 转换为 ELK Stack JSON
     */
    protected function toElkJson(array $logs, array $defaultMapping, ?array $customMapping): array
    {
        $prefix = config('siem-export.elk.index_prefix', 'huwutong-');
        $mapping = $customMapping ?? $defaultMapping;

        return array_map(function ($log) use ($mapping, $prefix) {
            $doc = ['_index' => $prefix . date('Y.m.d')];
            foreach ($mapping as $field => $elkField) {
                $val = $log[$field] ?? null;
                if ($elkField === '@timestamp' && $val) {
                    $val = is_string($val) ? $val : date('c', strtotime($val));
                }
                $this->setNestedValue($doc, $elkField, $val);
            }
            return $doc;
        }, $logs);
    }

    /**
     * 转换为阿里云 SLS 格式
     */
    protected function toSls(array $logs, array $defaultMapping, ?array $customMapping): array
    {
        $mapping = $customMapping ?? $defaultMapping;

        return array_map(function ($log) use ($mapping) {
            $item = [];
            foreach ($mapping as $field => $slsField) {
                $val = $log[$field] ?? '';
                if ($slsField === '__time__' && $val) {
                    $val = is_numeric($val) ? (int)$val : strtotime($val);
                }
                if (is_array($val)) $val = json_encode($val, JSON_UNESCAPED_UNICODE);
                $item[$slsField] = (string)$val;
            }
            // SLS 需要 __topic__ 和 __source__
            $item['__topic__'] = 'huwutong-audit';
            $item['__source__'] = $log['ip_address'] ?? 'internal';
            return $item;
        }, $logs);
    }

    /**
     * 获取仪表盘数据
     */
    public function getDashboard(int $tenantId): array
    {
        $connections = SiemConnection::where('tenant_id', $tenantId)->get();
        $total = $connections->count();
        $active = $connections->where('is_active', true)->count();
        $autoPush = $connections->where('auto_push', true)->count();

        $recentLogs = SiemPushLog::whereIn('siem_connection_id', $connections->pluck('id'))
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        return [
            'total_connections' => $total,
            'active_connections' => $active,
            'auto_push_enabled' => $autoPush,
            'format_distribution' => $connections->groupBy('format')->map->count()->toArray(),
            'recent_pushes' => $recentLogs->count(),
            'recent_failures' => $recentLogs->where('status', 'failed')->count(),
            'recent_success_rate' => $recentLogs->count() > 0
                ? round($recentLogs->where('status', 'success')->count() / $recentLogs->count() * 100, 1)
                : 100,
        ];
    }

    /**
     * 获取推送日志
     */
    public function getPushLogs(int $connectionId, ?string $status = null, int $limit = 50): array
    {
        $query = SiemPushLog::where('siem_connection_id', $connectionId);
        if ($status) {
            $query->where('status', $status);
        }
        return $query->orderBy('created_at', 'desc')->limit($limit)->get()->toArray();
    }

    /**
     * 获取连接统计
     */
    public function getConnectionStats(int $connectionId): array
    {
        $connection = SiemConnection::findOrFail($connectionId);

        $totalPushes = SiemPushLog::where('siem_connection_id', $connectionId)->count();
        $successPushes = SiemPushLog::where('siem_connection_id', $connectionId)->where('status', 'success')->count();
        $totalRecords = SiemPushLog::where('siem_connection_id', $connectionId)->sum('records_count');

        return [
            'total_pushes' => $totalPushes,
            'success_pushes' => $successPushes,
            'failed_pushes' => $totalPushes - $successPushes,
            'success_rate' => $totalPushes > 0 ? round($successPushes / $totalPushes * 100, 1) : 100,
            'total_records_pushed' => $totalRecords,
            'last_push_at' => $connection->last_push_at,
            'last_success_at' => $connection->last_success_at,
        ];
    }

    /**
     * 获取格式预览
     */
    public function getFormatPreview(string $format): array
    {
        $sample = [[
            'id' => 1,
            'event_type' => 'license.activated',
            'tenant_id' => 1,
            'user_id' => 42,
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0',
            'description' => 'License HWT-ENT-XXXXX 已激活',
            'created_at' => now()->toIso8601String(),
            'severity' => 'info',
            'metadata' => ['license_key' => 'HWT-ENT-XXXXX'],
        ]];

        return [
            'format' => $format,
            'sample' => $this->convertFormat($sample, $format, null),
            'field_mappings' => config("siem-export.field_mappings.{$format}", []),
        ];
    }

    /**
     * 构建认证头
     */
    protected function buildAuthHeaders(SiemConnection $connection): array
    {
        return match ($connection->auth_type) {
            'bearer_token' => [
                'Authorization' => 'Bearer ' . ($connection->auth_credentials ?? ''),
            ],
            'basic' => [
                'Authorization' => 'Basic ' . base64_encode($connection->auth_credentials ?? ':'),
            ],
            'api_key' => [
                'X-API-Key' => $connection->auth_credentials ?? '',
            ],
            default => [],
        };
    }

    /**
     * 记录推送日志
     */
    protected function logPush(int $connectionId, string $status, int $count, ?int $code = null, ?string $body = null, ?string $error = null, float $duration = 0): void
    {
        try {
            SiemPushLog::create([
                'siem_connection_id' => $connectionId,
                'status' => $status,
                'records_count' => $count,
                'response_code' => $code,
                'response_body' => $body ? substr($body, 0, 1000) : null,
                'error_message' => $error ? substr($error, 0, 500) : null,
                'duration_ms' => round($duration, 2),
            ]);
        } catch (\Exception $e) {
            LogFacade::warning('Failed to write SIEM push log: ' . $e->getMessage());
        }
    }

    /**
     * 获取审计日志
     */
    protected function fetchAuditLogs(int $tenantId, array $filters, int $limit): array
    {
        $query = Log::where('tenant_id', $tenantId);

        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }
        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        return $query->orderBy('created_at', 'desc')
            ->limit(min($limit, config('siem-export.max_records_per_push', 5000)))
            ->get()
            ->toArray();
    }

    /**
     * 设置嵌套数组值（支持点号路径）
     */
    protected function setNestedValue(array &$array, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $current = &$array;
        foreach ($keys as $key) {
            if ($key === '@timestamp' || (count($keys) > 1 && $key === end($keys))) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }
}

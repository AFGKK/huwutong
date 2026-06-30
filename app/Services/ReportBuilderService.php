<?php

namespace App\Services;

use App\Models\CustomReport;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Subscription;
use App\Models\ReportDashboard;
use App\Models\ReportSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 自定义报表生成器服务
 *
 * 提供：
 * - 多数据源报表生成
 * - 指标/维度/过滤条件组合
 * - 图表数据聚合
 * - 定时导出与快照
 * - 仪表盘管理
 */
class ReportBuilderService
{
    // ─── 数据源定义 ───

    /**
     * 获取可用的数据源信息
     */
    public function getDataSources(): array
    {
        return [
            'subscriptions' => [
                'label' => '订阅数据',
                'description' => '订阅信息、状态、金额、周期',
                'metrics' => [
                    'count' => ['label' => '订阅数', 'type' => 'count', 'default' => true],
                    'total_revenue' => ['label' => '总收入', 'type' => 'sum', 'field' => 'total_paid', 'format' => 'currency'],
                    'avg_price' => ['label' => '平均价格', 'type' => 'avg', 'field' => 'price', 'format' => 'currency'],
                    'active_count' => ['label' => '活跃订阅数', 'type' => 'count_filtered', 'filter' => ['status' => 'active']],
                ],
                'dimensions' => [
                    'status' => ['label' => '状态', 'field' => 'status'],
                    'billing_period' => ['label' => '计费周期', 'field' => 'billing_period'],
                    'plan' => ['label' => '方案', 'field' => 'plan'],
                    'currency' => ['label' => '货币', 'field' => 'currency'],
                    'created_at' => ['label' => '创建时间', 'field' => 'created_at', 'type' => 'date'],
                    'starts_at' => ['label' => '开始时间', 'field' => 'starts_at', 'type' => 'date'],
                ],
            ],
            'invoices' => [
                'label' => '发票数据',
                'description' => '发票金额、状态、支付方式',
                'metrics' => [
                    'count' => ['label' => '发票数', 'type' => 'count', 'default' => true],
                    'total_amount' => ['label' => '总金额', 'type' => 'sum', 'field' => 'amount', 'format' => 'currency'],
                    'avg_amount' => ['label' => '平均金额', 'type' => 'avg', 'field' => 'amount', 'format' => 'currency'],
                    'paid_count' => ['label' => '已支付数', 'type' => 'count_filtered', 'filter' => ['paid' => true]],
                    'tax_total' => ['label' => '总税额', 'type' => 'sum', 'field' => 'tax_amount', 'format' => 'currency'],
                    'discount_total' => ['label' => '总折扣', 'type' => 'sum', 'field' => 'discount_amount', 'format' => 'currency'],
                ],
                'dimensions' => [
                    'status' => ['label' => '状态', 'field' => 'status'],
                    'payment_method' => ['label' => '支付方式', 'field' => 'payment_method'],
                    'currency' => ['label' => '货币', 'field' => 'currency'],
                    'billing_reason' => ['label' => '原因', 'field' => 'billing_reason'],
                    'created_at' => ['label' => '创建时间', 'field' => 'created_at', 'type' => 'date'],
                    'paid_at' => ['label' => '支付时间', 'field' => 'paid_at', 'type' => 'date'],
                ],
            ],
            'licenses' => [
                'label' => 'License 数据',
                'description' => '许可证信息、状态、类型',
                'metrics' => [
                    'count' => ['label' => 'License 数', 'type' => 'count', 'default' => true],
                    'active_count' => ['label' => '活跃数', 'type' => 'count_filtered', 'filter' => ['status' => 'active']],
                    'total_seats' => ['label' => '总 Seat 数', 'type' => 'sum', 'field' => 'seats'],
                    'avg_seats' => ['label' => '平均 Seat', 'type' => 'avg', 'field' => 'seats'],
                    'total_devices' => ['label' => '总设备限额', 'type' => 'sum', 'field' => 'max_devices'],
                ],
                'dimensions' => [
                    'status' => ['label' => '状态', 'field' => 'status'],
                    'type' => ['label' => '类型', 'field' => 'type'],
                    'product_id' => ['label' => '产品', 'field' => 'product_id', 'relation' => 'product.name'],
                    'created_at' => ['label' => '创建时间', 'field' => 'created_at', 'type' => 'date'],
                    'expires_at' => ['label' => '过期时间', 'field' => 'expires_at', 'type' => 'date'],
                ],
            ],
            'customers' => [
                'label' => '客户数据',
                'description' => '客户信息、地域分布',
                'metrics' => [
                    'count' => ['label' => '客户数', 'type' => 'count', 'default' => true],
                    'active_count' => ['label' => '活跃客户', 'type' => 'count_filtered', 'filter' => ['status' => 'active']],
                ],
                'dimensions' => [
                    'country' => ['label' => '国家', 'field' => 'country'],
                    'status' => ['label' => '状态', 'field' => 'status'],
                    'created_at' => ['label' => '创建时间', 'field' => 'created_at', 'type' => 'date'],
                ],
            ],
            'activations' => [
                'label' => '激活数据',
                'description' => '设备激活记录',
                'metrics' => [
                    'count' => ['label' => '激活数', 'type' => 'count', 'default' => true],
                    'unique_devices' => ['label' => '唯一设备', 'type' => 'count_distinct', 'field' => 'device_id'],
                ],
                'dimensions' => [
                    'created_at' => ['label' => '激活时间', 'field' => 'created_at', 'type' => 'date'],
                ],
            ],
            'churn' => [
                'label' => '流失分析',
                'description' => '客户流失数据',
                'metrics' => [
                    'churned_count' => ['label' => '流失数', 'type' => 'count_filtered', 'filter' => ['status' => 'canceled']],
                    'churn_rate' => ['label' => '流失率', 'type' => 'computed', 'format' => 'percentage'],
                ],
                'dimensions' => [
                    'canceled_at' => ['label' => '取消时间', 'field' => 'canceled_at', 'type' => 'date'],
                    'cancellation_reason' => ['label' => '取消原因', 'field' => 'cancellation_reason'],
                ],
            ],
            'audit_logs' => [
                'label' => '审计日志',
                'description' => '操作审计记录',
                'metrics' => [
                    'count' => ['label' => '日志数', 'type' => 'count', 'default' => true],
                ],
                'dimensions' => [
                    'type' => ['label' => '类型', 'field' => 'type'],
                    'action' => ['label' => '动作', 'field' => 'action'],
                    'created_at' => ['label' => '时间', 'field' => 'created_at', 'type' => 'date'],
                ],
            ],
        ];
    }

    // ─── 报表生成 ───

    /**
     * 生成报表数据
     */
    public function generateReportData(CustomReport $report): array
    {
        $dataSource = $report->data_source;
        $metrics = $report->metrics ?? [];
        $dimensions = $report->dimensions ?? [];
        $filters = $report->filters ?? [];
        $sorts = $report->sorts ?? [];

        $query = $this->buildQuery($dataSource, $filters);

        $rawData = $query->get()->toArray();

        // 按维度分组聚合
        $aggregated = $this->aggregateData($rawData, $metrics, $dimensions);

        // 排序
        $aggregated = $this->sortData($aggregated, $sorts);

        // 计算汇总
        $summary = $this->calculateSummary($aggregated, $metrics);

        // 图表格式化
        $chartData = $this->formatChartData($aggregated, $report->chart_type, $metrics, $dimensions);

        return [
            'rows' => $aggregated,
            'total_rows' => count($aggregated),
            'summary' => $summary,
            'chart' => $chartData,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * 构建查询
     */
    protected function buildQuery(string $dataSource, array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $query = match ($dataSource) {
            'subscriptions' => Subscription::query()->with('product'),
            'invoices' => Invoice::query(),
            'licenses' => License::query()->with('product'),
            'customers' => \App\Models\Customer::query(),
            'activations' => \App\Models\LicenseActivation::query(),
            'churn' => Subscription::query()->whereNotNull('canceled_at'),
            'audit_logs' => \App\Models\Log::query(),
            default => throw new \InvalidArgumentException("不支持的数据源: {$dataSource}"),
        };

        // 应用过滤条件
        foreach ($filters as $field => $value) {
            if ($value === '' || $value === null) continue;

            if (is_array($value) && isset($value['type'])) {
                match ($value['type']) {
                    'date_range' => $this->applyDateRangeFilter($query, $field, $value),
                    'in' => $query->whereIn($field, $value['values']),
                    'like' => $query->where($field, 'like', "%{$value['value']}%"),
                    default => $query->where($field, $value['value'] ?? ''),
                };
            } else {
                $query->where($field, $value);
            }
        }

        return $query;
    }

    protected function applyDateRangeFilter($query, string $field, array $value): void
    {
        if (!empty($value['from'])) {
            $query->where($field, '>=', $value['from']);
        }
        if (!empty($value['to'])) {
            $query->where($field, '<=', $value['to'] . ' 23:59:59');
        }
    }

    /**
     * 聚合数据
     */
    protected function aggregateData(array $rawData, array $metrics, array $dimensions): array
    {
        if (empty($dimensions)) {
            // 无维度，直接计算指标
            return [$this->computeMetrics($rawData, $metrics)];
        }

        // 按维度分组
        $groups = [];
        foreach ($rawData as $row) {
            $key = $this->buildGroupKey($row, $dimensions);
            $groups[$key][] = $row;
        }

        $results = [];
        foreach ($groups as $key => $groupData) {
            $result = $this->computeMetrics($groupData, $metrics);
            // 添加维度值
            $firstRow = $groupData[0];
            foreach ($dimensions as $dim) {
                $field = is_string($dim) ? $dim : ($dim['field'] ?? $dim);
                $result['_dim_' . $field] = $firstRow[$field] ?? $key;
            }
            $results[] = $result;
        }

        return $results;
    }

    protected function buildGroupKey(array $row, array $dimensions): string
    {
        $parts = [];
        foreach ($dimensions as $dim) {
            $field = is_string($dim) ? $dim : ($dim['field'] ?? $dim);
            $parts[] = $row[$field] ?? 'null';
        }
        return implode('|', $parts);
    }

    protected function computeMetrics(array $rows, array $metrics): array
    {
        $result = [];
        foreach ($metrics as $key => $metric) {
            $metricDef = is_string($metric) ? ['type' => 'count', 'field' => $metric] : $metric;
            $type = $metricDef['type'] ?? 'count';
            $field = $metricDef['field'] ?? null;

            $result[$key] = match ($type) {
                'count' => count($rows),
                'sum' => $field ? array_sum(array_column($rows, $field)) : 0,
                'avg' => $field && count($rows) > 0 ? array_sum(array_column($rows, $field)) / count($rows) : 0,
                'count_filtered' => $this->countFiltered($rows, $metricDef['filter'] ?? []),
                'count_distinct' => $field ? count(array_unique(array_column($rows, $field))) : 0,
                'max' => $field && count($rows) > 0 ? max(array_column($rows, $field)) : 0,
                'min' => $field && count($rows) > 0 ? min(array_column($rows, $field)) : 0,
                'computed' => $this->computeMetric($rows, $metricDef),
                default => count($rows),
            };
        }
        return $result;
    }

    protected function countFiltered(array $rows, array $filter): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $match = true;
            foreach ($filter as $field => $value) {
                if (($row[$field] ?? null) != $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) $count++;
        }
        return $count;
    }

    protected function computeMetric(array $rows, array $def): float
    {
        // 自定义计算指标
        $type = $def['field'] ?? 'churn_rate';
        return match ($type) {
            'churn_rate' => $this->computeChurnRate($rows),
            default => 0,
        };
    }

    protected function computeChurnRate(array $rows): float
    {
        $total = count($rows);
        if ($total === 0) return 0;
        $churned = count(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'canceled'));
        return round(($churned / $total) * 100, 2);
    }

    /**
     * 排序
     */
    protected function sortData(array $data, array $sorts): array
    {
        if (empty($sorts)) return $data;

        usort($data, function ($a, $b) use ($sorts) {
            foreach ($sorts as $sort) {
                $field = is_string($sort) ? $sort : ($sort['field'] ?? '');
                $dir = is_array($sort) ? ($sort['dir'] ?? 'asc') : 'asc';
                $aVal = $a[$field] ?? 0;
                $bVal = $b[$field] ?? 0;
                if ($aVal != $bVal) {
                    return $dir === 'desc' ? ($bVal <=> $aVal) : ($aVal <=> $bVal);
                }
            }
            return 0;
        });

        return $data;
    }

    /**
     * 计算汇总
     */
    protected function calculateSummary(array $data, array $metrics): array
    {
        if (empty($data)) return [];

        $summary = [];
        foreach ($metrics as $key => $metric) {
            $metricDef = is_string($metric) ? ['type' => 'count'] : $metric;
            $type = $metricDef['type'] ?? 'count';
            $format = $metricDef['format'] ?? null;

            $values = array_column($data, $key);
            $values = array_filter($values, fn($v) => is_numeric($v));

            if (!empty($values)) {
                $summary[$key] = [
                    'total' => array_sum($values),
                    'avg' => round(array_sum($values) / count($values), 2),
                    'min' => min($values),
                    'max' => max($values),
                    'format' => $format,
                ];
            }
        }

        return $summary;
    }

    // ─── 图表格式化 ───

    protected function formatChartData(array $data, string $chartType, array $metrics, array $dimensions): array
    {
        if (empty($data) || empty($metrics)) {
            return ['labels' => [], 'datasets' => []];
        }

        $metricKeys = array_keys($metrics);
        $dimKeys = [];
        foreach ($dimensions as $dim) {
            $field = is_string($dim) ? $dim : ($dim['field'] ?? $dim);
            $dimKeys[] = '_dim_' . $field;
        }

        $labels = [];
        $datasets = [];

        if ($chartType === 'pie' || $chartType === 'radar') {
            $labels = array_map(fn($row) => implode(' - ', array_intersect_key($row, array_flip($dimKeys))), $data);
            $datasets = array_map(fn($key) => [
                'label' => $metrics[$key]['label'] ?? $key,
                'data' => array_column($data, $key),
            ], $metricKeys);
        } elseif ($chartType === 'number') {
            // 数字卡片
            foreach ($metricKeys as $key) {
                $datasets[] = [
                    'label' => $metrics[$key]['label'] ?? $key,
                    'value' => array_sum(array_column($data, $key)),
                    'format' => $metrics[$key]['format'] ?? null,
                ];
            }
        } else {
            // bar, line, area
            $labels = array_map(fn($row) => implode(' - ', array_intersect_key($row, array_flip($dimKeys))), $data);
            foreach ($metricKeys as $key) {
                $datasets[] = [
                    'label' => $metrics[$key]['label'] ?? $key,
                    'data' => array_column($data, $key),
                    'format' => $metrics[$key]['format'] ?? null,
                ];
            }
        }

        return ['labels' => $labels, 'datasets' => $datasets, 'type' => $chartType];
    }

    // ─── 报表 CRUD ───

    public function createReport(array $data): CustomReport
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']) . '-' . Str::random(6);
        return CustomReport::create($data);
    }

    public function updateReport(CustomReport $report, array $data): CustomReport
    {
        $report->update($data);
        return $report->fresh();
    }

    public function deleteReport(CustomReport $report): void
    {
        $report->snapshots()->delete();
        $report->delete();
    }

    // ─── 报表快照 ───

    public function generateSnapshot(CustomReport $report): ReportSnapshot
    {
        $snapshot = ReportSnapshot::create([
            'report_id' => $report->id,
            'status' => 'generating',
        ]);

        try {
            $data = $this->generateReportData($report);

            $snapshot->update([
                'status' => 'completed',
                'snapshot_data' => $data,
                'summary' => $data['summary'] ?? null,
                'row_count' => $data['total_rows'] ?? 0,
                'generated_at' => now(),
            ]);

            $report->update(['last_generated_at' => now()]);

            return $snapshot->fresh();
        } catch (\Exception $e) {
            $snapshot->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 导出报表
     */
    public function exportReport(CustomReport $report, string $format = 'csv'): array
    {
        $snapshot = $this->generateSnapshot($report);
        $data = $snapshot->snapshot_data;

        if (!$data || empty($data['rows'])) {
            throw new \RuntimeException('报表数据为空');
        }

        $content = match ($format) {
            'csv' => $this->toCsv($data),
            'json' => $this->toJson($data),
            default => throw new \InvalidArgumentException("不支持的导出格式: {$format}"),
        };

        $filename = Str::slug($report->name) . '-' . now()->format('YmdHis') . '.' . $format;
        $path = "reports/{$filename}";
        Storage::disk('local')->put($path, $content);

        $snapshot->update([
            'file_path' => $path,
            'file_format' => $format,
            'file_size' => strlen($content),
        ]);

        return [
            'path' => $path,
            'filename' => $filename,
            'url' => Storage::disk('local')->url($path),
            'size' => strlen($content),
            'snapshot_id' => $snapshot->id,
        ];
    }

    protected function toCsv(array $data): string
    {
        $output = fopen('php://temp', 'r+');

        // 提取表头（维度和指标）
        $headers = [];
        if (!empty($data['rows'])) {
            $firstRow = $data['rows'][0];
            foreach ($firstRow as $key => $value) {
                if (!str_starts_with($key, '_dim_')) {
                    $headers[] = $key;
                } else {
                    $headers[] = substr($key, 5);
                }
            }
        }
        fputcsv($output, $headers);

        foreach ($data['rows'] as $row) {
            $values = [];
            foreach ($headers as $header) {
                $dimKey = '_dim_' . $header;
                $values[] = $row[$dimKey] ?? $row[$header] ?? '';
            }
            fputcsv($output, $values);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    protected function toJson(array $data): string
    {
        return json_encode([
            'exported_at' => now()->toIso8601String(),
            'report' => [
                'rows' => $data['rows'] ?? [],
                'summary' => $data['summary'] ?? [],
                'total_rows' => $data['total_rows'] ?? 0,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    // ─── 仪表盘 ───

    public function getDashboards(int $userId): array
    {
        $dashboards = ReportDashboard::where('user_id', $userId)
            ->orWhere('is_shared', true)
            ->orderBy('sort_order')
            ->orderByDesc('is_default')
            ->get()
            ->all();

        // 如果没有看板，创建默认看板
        if (empty($dashboards)) {
            $dashboards[] = $this->createDefaultDashboard($userId);
        }

        return $dashboards;
    }

    public function createDashboard(array $data): ReportDashboard
    {
        return ReportDashboard::create($data);
    }

    public function updateDashboard(ReportDashboard $dashboard, array $data): ReportDashboard
    {
        $dashboard->update($data);
        return $dashboard->fresh();
    }

    public function deleteDashboard(ReportDashboard $dashboard): void
    {
        $dashboard->delete();
    }

    protected function createDefaultDashboard(int $userId): ReportDashboard
    {
        return ReportDashboard::create([
            'user_id' => $userId,
            'name' => '默认看板',
            'description' => '系统自动创建的默认看板',
            'layout' => ['widgets' => []],
            'is_default' => true,
        ]);
    }

    // ─── 仪表盘 ───

    /**
     * 获取报表生成器仪表盘数据
     */
    public function getDashboard(int $userId, ?int $tenantId = null): array
    {
        $reports = CustomReport::where('user_id', $userId)
            ->when($tenantId, fn($q) => $q->orWhere('tenant_id', $tenantId))
            ->count();

        $templates = CustomReport::where('is_template', true)->count();
        $scheduled = CustomReport::where('is_scheduled', true)->count();
        $snapshots = ReportSnapshot::whereIn('report_id', function ($q) use ($userId) {
            $q->select('id')->from('custom_reports')->where('user_id', $userId);
        })->count();

        $recent = CustomReport::where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->all();

        $dataSources = $this->getDataSources();

        return [
            'stats' => [
                'total_reports' => $reports,
                'total_templates' => $templates,
                'scheduled_count' => $scheduled,
                'total_snapshots' => $snapshots,
            ],
            'recent_reports' => $recent,
            'data_sources' => $dataSources,
            'categories' => CustomReport::CATEGORIES,
            'chart_types' => CustomReport::CHART_TYPES,
        ];
    }
}

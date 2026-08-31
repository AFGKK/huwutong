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
        $t = fn(string $key) => __('app.admin.report_builder.' . $key);

        return [
            'subscriptions' => [
                'label' => $t('data_sources.subscriptions.label'),
                'description' => $t('data_sources.subscriptions.description'),
                'metrics' => [
                    'count' => ['label' => $t('metrics.subscriptions.count'), 'type' => 'count', 'default' => true],
                    'total_revenue' => ['label' => $t('metrics.subscriptions.total_revenue'), 'type' => 'sum', 'field' => 'total_paid', 'format' => 'currency'],
                    'avg_price' => ['label' => $t('metrics.subscriptions.avg_price'), 'type' => 'avg', 'field' => 'price', 'format' => 'currency'],
                    'active_count' => ['label' => $t('metrics.subscriptions.active_count'), 'type' => 'count_filtered', 'filter' => ['status' => 'active']],
                ],
                'dimensions' => [
                    'status' => ['label' => $t('dimensions.subscriptions.status'), 'field' => 'status'],
                    'billing_period' => ['label' => $t('dimensions.subscriptions.billing_period'), 'field' => 'billing_period'],
                    'plan' => ['label' => $t('dimensions.subscriptions.plan'), 'field' => 'plan'],
                    'currency' => ['label' => $t('dimensions.subscriptions.currency'), 'field' => 'currency'],
                    'created_at' => ['label' => $t('dimensions.subscriptions.created_at'), 'field' => 'created_at', 'type' => 'date'],
                    'starts_at' => ['label' => $t('dimensions.subscriptions.starts_at'), 'field' => 'starts_at', 'type' => 'date'],
                ],
            ],
            'invoices' => [
                'label' => $t('data_sources.invoices.label'),
                'description' => $t('data_sources.invoices.description'),
                'metrics' => [
                    'count' => ['label' => $t('metrics.invoices.count'), 'type' => 'count', 'default' => true],
                    'total_amount' => ['label' => $t('metrics.invoices.total_amount'), 'type' => 'sum', 'field' => 'amount', 'format' => 'currency'],
                    'avg_amount' => ['label' => $t('metrics.invoices.avg_amount'), 'type' => 'avg', 'field' => 'amount', 'format' => 'currency'],
                    'paid_count' => ['label' => $t('metrics.invoices.paid_count'), 'type' => 'count_filtered', 'filter' => ['paid' => true]],
                    'tax_total' => ['label' => $t('metrics.invoices.tax_total'), 'type' => 'sum', 'field' => 'tax_amount', 'format' => 'currency'],
                    'discount_total' => ['label' => $t('metrics.invoices.discount_total'), 'type' => 'sum', 'field' => 'discount_amount', 'format' => 'currency'],
                ],
                'dimensions' => [
                    'status' => ['label' => $t('dimensions.invoices.status'), 'field' => 'status'],
                    'payment_method' => ['label' => $t('dimensions.invoices.payment_method'), 'field' => 'payment_method'],
                    'currency' => ['label' => $t('dimensions.invoices.currency'), 'field' => 'currency'],
                    'billing_reason' => ['label' => $t('dimensions.invoices.billing_reason'), 'field' => 'billing_reason'],
                    'created_at' => ['label' => $t('dimensions.invoices.created_at'), 'field' => 'created_at', 'type' => 'date'],
                    'paid_at' => ['label' => $t('dimensions.invoices.paid_at'), 'field' => 'paid_at', 'type' => 'date'],
                ],
            ],
            'licenses' => [
                'label' => $t('data_sources.licenses.label'),
                'description' => $t('data_sources.licenses.description'),
                'metrics' => [
                    'count' => ['label' => $t('metrics.licenses.count'), 'type' => 'count', 'default' => true],
                    'active_count' => ['label' => $t('metrics.licenses.active_count'), 'type' => 'count_filtered', 'filter' => ['status' => 'active']],
                    'total_seats' => ['label' => $t('metrics.licenses.total_seats'), 'type' => 'sum', 'field' => 'seats'],
                    'avg_seats' => ['label' => $t('metrics.licenses.avg_seats'), 'type' => 'avg', 'field' => 'seats'],
                    'total_devices' => ['label' => $t('metrics.licenses.total_devices'), 'type' => 'sum', 'field' => 'max_devices'],
                ],
                'dimensions' => [
                    'status' => ['label' => $t('dimensions.licenses.status'), 'field' => 'status'],
                    'type' => ['label' => $t('dimensions.licenses.type'), 'field' => 'type'],
                    'product_id' => ['label' => $t('dimensions.licenses.product_id'), 'field' => 'product_id', 'relation' => 'product.name'],
                    'created_at' => ['label' => $t('dimensions.licenses.created_at'), 'field' => 'created_at', 'type' => 'date'],
                    'expires_at' => ['label' => $t('dimensions.licenses.expires_at'), 'field' => 'expires_at', 'type' => 'date'],
                ],
            ],
            'customers' => [
                'label' => $t('data_sources.customers.label'),
                'description' => $t('data_sources.customers.description'),
                'metrics' => [
                    'count' => ['label' => $t('metrics.customers.count'), 'type' => 'count', 'default' => true],
                    'active_count' => ['label' => $t('metrics.customers.active_count'), 'type' => 'count_filtered', 'filter' => ['status' => 'active']],
                ],
                'dimensions' => [
                    'country' => ['label' => $t('dimensions.customers.country'), 'field' => 'country'],
                    'status' => ['label' => $t('dimensions.customers.status'), 'field' => 'status'],
                    'created_at' => ['label' => $t('dimensions.customers.created_at'), 'field' => 'created_at', 'type' => 'date'],
                ],
            ],
            'activations' => [
                'label' => $t('data_sources.activations.label'),
                'description' => $t('data_sources.activations.description'),
                'metrics' => [
                    'count' => ['label' => $t('metrics.activations.count'), 'type' => 'count', 'default' => true],
                    'unique_devices' => ['label' => $t('metrics.activations.unique_devices'), 'type' => 'count_distinct', 'field' => 'device_id'],
                ],
                'dimensions' => [
                    'created_at' => ['label' => $t('dimensions.activations.created_at'), 'field' => 'created_at', 'type' => 'date'],
                ],
            ],
            'churn' => [
                'label' => $t('data_sources.churn.label'),
                'description' => $t('data_sources.churn.description'),
                'metrics' => [
                    'churned_count' => ['label' => $t('metrics.churn.churned_count'), 'type' => 'count_filtered', 'filter' => ['status' => 'canceled']],
                    'churn_rate' => ['label' => $t('metrics.churn.churn_rate'), 'type' => 'computed', 'format' => 'percentage'],
                ],
                'dimensions' => [
                    'canceled_at' => ['label' => $t('dimensions.churn.canceled_at'), 'field' => 'canceled_at', 'type' => 'date'],
                    'cancellation_reason' => ['label' => $t('dimensions.churn.cancellation_reason'), 'field' => 'cancellation_reason'],
                ],
            ],
            'audit_logs' => [
                'label' => $t('data_sources.audit_logs.label'),
                'description' => $t('data_sources.audit_logs.description'),
                'metrics' => [
                    'count' => ['label' => $t('metrics.audit_logs.count'), 'type' => 'count', 'default' => true],
                ],
                'dimensions' => [
                    'type' => ['label' => $t('dimensions.audit_logs.type'), 'field' => 'type'],
                    'action' => ['label' => $t('dimensions.audit_logs.action'), 'field' => 'action'],
                    'created_at' => ['label' => $t('dimensions.audit_logs.created_at'), 'field' => 'created_at', 'type' => 'date'],
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
            default => throw new \InvalidArgumentException(__('app.admin.report_builder.unsupported_source', ['source' => $dataSource])),
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
            throw new \RuntimeException(__('app.admin.report_builder.empty_data'));
        }

        $content = match ($format) {
            'csv' => $this->toCsv($data),
            'json' => $this->toJson($data),
            default => throw new \InvalidArgumentException(__('app.admin.report_builder.unsupported_format', ['format' => $format])),
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
            'name' => __('app.admin.report_builder.default_dashboard_name'),
            'description' => __('app.admin.report_builder.default_dashboard_description'),
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

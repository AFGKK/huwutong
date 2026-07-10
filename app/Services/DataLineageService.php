<?php

namespace App\Services;

use App\Models\DataLineageRecord;
use App\Support\DbSql;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 数据血缘追踪服务 (M2-113)
 *
 * 记录关键数据（License Key / 客户PII / 设备指纹等）的完整生命周期：
 * - 创建/读取/更新/导出/归档/删除 等事件
 * - 数据来源、流向、变更详情
 * - 关联链路（parent-child + trace_id）
 * - 仪表盘聚合统计
 */
class DataLineageService
{
    /**
     * 记录一条数据血缘事件
     */
    public function record(array $data): DataLineageRecord
    {
        $data['tenant_id'] ??= request()->user()?->tenant_id;
        $data['trace_id'] ??= (string) Str::uuid();
        $data['recorded_at'] ??= now();

        // 自动填充 actor
        if (!isset($data['actor_id']) && request()->user()) {
            $data['actor_id'] = request()->user()->id;
            $data['actor_type'] ??= 'user';
        }

        return DataLineageRecord::create($data);
    }

    /**
     * 批量记录多条事件（同请求链路）
     */
    public function recordBatch(array $events): Collection
    {
        $traceId = (string) Str::uuid();
        $records = [];
        foreach ($events as $i => &$event) {
            $event['trace_id'] ??= $traceId;
            $event['recorded_at'] ??= now();
            if ($i > 0 && !isset($event['parent_record_id']) && isset($records[$i - 1])) {
                $event['parent_record_id'] = $records[$i - 1]->id;
            }
        }

        $records = [];
        foreach ($events as $event) {
            $records[] = $this->record($event);
        }

        return new Collection($records);
    }

    /**
     * 获取指定对象的数据血缘链路（按时间倒序）
     */
    public function getLineage(string $trackableType, string $trackableId, array $options = []): array
    {
        $query = DataLineageRecord::byTrackable($trackableType, $trackableId);

        if ($tenantId = $options['tenant_id'] ?? null) {
            $query->byTenant($tenantId);
        }
        if ($category = $options['data_category'] ?? null) {
            $query->byCategory($category);
        }
        if ($eventType = $options['event_type'] ?? null) {
            $query->byEvent($eventType);
        }
        if ($from = $options['from'] ?? null) {
            $query->where('recorded_at', '>=', $from);
        }
        if ($to = $options['to'] ?? null) {
            $query->where('recorded_at', '<=', $to);
        }

        $perPage = $options['per_page'] ?? 50;
        $page = $options['page'] ?? 1;

        $records = $query->with('actor:id,name,email')
            ->orderBy('recorded_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // 构建血缘关系图
        $allIds = $records->pluck('id')->toArray();
        $parentIds = $records->pluck('parent_record_id')->filter()->unique()->toArray();
        $missingParents = array_diff($parentIds, $allIds);

        $parents = [];
        if (!empty($missingParents)) {
            $parents = DataLineageRecord::whereIn('id', $missingParents)
                ->get(['id', 'trackable_type', 'trackable_id', 'trackable_label', 'event_type', 'event_label', 'recorded_at'])
                ->keyBy('id')
                ->toArray();
        }

        return [
            'items' => $records->items(),
            'pagination' => [
                'page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
            'graph' => $this->buildGraph($records->items(), $parents),
        ];
    }

    /**
     * 构建血缘关系图（节点 + 边）
     */
    public function buildGraph(array $records, array $extraParents = []): array
    {
        $nodes = [];
        $edges = [];

        foreach ($records as $r) {
            $nodeId = 'record_' . $r->id;
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'label' => $r->event_label ?: $r->event_type,
                'type' => $r->trackable_type,
                'category' => $r->data_category,
                'event' => $r->event_type,
                'sensitivity' => $r->sensitivity,
                'recorded_at' => $r->recorded_at?->toIso8601String(),
                'actor_name' => $r->actor?->name,
                'target_system' => $r->target_system,
            ];

            if ($r->parent_record_id) {
                $parentId = 'record_' . $r->parent_record_id;
                $edges[] = [
                    'from' => $parentId,
                    'to' => $nodeId,
                    'label' => '流向',
                ];
            }
        }

        foreach ($extraParents as $id => $parent) {
            $nodeId = 'record_' . $id;
            if (!isset($nodes[$nodeId])) {
                $nodes[$nodeId] = [
                    'id' => $nodeId,
                    'label' => $parent['event_label'] ?? $parent['event_type'] ?? '',
                    'type' => $parent['trackable_type'] ?? '',
                    'category' => $parent['data_category'] ?? '',
                    'event' => $parent['event_type'] ?? '',
                    'sensitivity' => $parent['sensitivity'] ?? '',
                    'recorded_at' => $parent['recorded_at'] ?? null,
                    'actor_name' => null,
                    'target_system' => null,
                    'is_parent' => true,
                ];
            }
        }

        return [
            'nodes' => array_values($nodes),
            'edges' => $edges,
        ];
    }

    /**
     * 仪表盘聚合统计
     */
    public function dashboard(int $tenantId): array
    {
        $base = DataLineageRecord::byTenant($tenantId);

        $totalRecords = (clone $base)->count();
        $totalTracked = (clone $base)
            ->select(DB::raw('COUNT(DISTINCT '.DbSql::concat('trackable_type', "':'", 'trackable_id').') as cnt'))
            ->value('cnt');

        // 按数据类别统计
        $byCategory = (clone $base)
            ->select('data_category', DB::raw('COUNT(*) as cnt'))
            ->groupBy('data_category')
            ->orderByDesc('cnt')
            ->pluck('cnt', 'data_category')
            ->toArray();

        // 按事件类型统计
        $byEvent = (clone $base)
            ->select('event_type', DB::raw('COUNT(*) as cnt'))
            ->groupBy('event_type')
            ->orderByDesc('cnt')
            ->pluck('cnt', 'event_type')
            ->toArray();

        // 近7天趋势
        $weekly = (clone $base)
            ->where('recorded_at', '>=', now()->subDays(7))
            ->select(DB::raw('DATE(recorded_at) as date'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('cnt', 'date')
            ->toArray();

        // 数据敏感度分布
        $bySensitivity = (clone $base)
            ->select('sensitivity', DB::raw('COUNT(*) as cnt'))
            ->groupBy('sensitivity')
            ->pluck('cnt', 'sensitivity')
            ->toArray();

        // 各类别最新记录时间
        $latestByCategory = (clone $base)
            ->select('data_category', DB::raw('MAX(recorded_at) as latest'))
            ->groupBy('data_category')
            ->pluck('latest', 'data_category')
            ->toArray();

        return [
            'total_records' => $totalRecords,
            'total_tracked_objects' => (int) $totalTracked,
            'by_category' => $byCategory,
            'by_event' => $byEvent,
            'weekly_trend' => $weekly,
            'by_sensitivity' => $bySensitivity,
            'latest_by_category' => $latestByCategory,
        ];
    }

    /**
     * 查询血缘记录（高级筛选）
     */
    public function queryRecords(int $tenantId, array $filters = []): array
    {
        $query = DataLineageRecord::byTenant($tenantId)->with('actor:id,name,email');

        if ($type = $filters['trackable_type'] ?? null) {
            $query->where('trackable_type', $type);
        }
        if ($id = $filters['trackable_id'] ?? null) {
            $query->where('trackable_id', $id);
        }
        if ($category = $filters['data_category'] ?? null) {
            $query->where('data_category', $category);
        }
        if ($event = $filters['event_type'] ?? null) {
            $query->where('event_type', $event);
        }
        if ($sensitivity = $filters['sensitivity'] ?? null) {
            $query->where('sensitivity', $sensitivity);
        }
        if ($actorId = $filters['actor_id'] ?? null) {
            $query->where('actor_id', $actorId);
        }
        if ($source = $filters['source_system'] ?? null) {
            $query->where('source_system', $source);
        }
        if ($search = $filters['search'] ?? null) {
            $query->where(function ($q) use ($search) {
                $q->where('trackable_label', 'like', "%{$search}%")
                  ->orWhere('event_label', 'like', "%{$search}%")
                  ->orWhere('trackable_id', 'like', "%{$search}%");
            });
        }
        if ($from = $filters['from'] ?? null) {
            $query->where('recorded_at', '>=', $from);
        }
        if ($to = $filters['to'] ?? null) {
            $query->where('recorded_at', '<=', $to);
        }

        $perPage = $filters['per_page'] ?? 20;
        $page = $filters['page'] ?? 1;

        $records = $query->orderBy('recorded_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $records->items(),
            'pagination' => [
                'page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
        ];
    }

    /**
     * 获取某条记录的完整血缘链路（含所有祖先）
     */
    public function getLineageChain(int $recordId, ?int $tenantId = null): array
    {
        $query = DataLineageRecord::query();
        if ($tenantId) {
            $query->byTenant($tenantId);
        }

        $record = $query->with('actor:id,name,email')->findOrFail($recordId);

        // 向上追溯所有祖先
        $ancestors = [];
        $current = $record;
        while ($current->parent_record_id) {
            $parent = DataLineageRecord::with('actor:id,name,email')
                ->when($tenantId, fn($q) => $q->byTenant($tenantId))
                ->find($current->parent_record_id);
            if (!$parent) break;
            $ancestors[] = $parent;
            $current = $parent;
        }

        // 向下查找所有后代
        $descendants = DataLineageRecord::where('parent_record_id', $recordId)
            ->orWhere(function ($q) use ($recordId, $tenantId) {
                $q->whereIn('id', function ($sub) use ($recordId, $tenantId) {
                    $sub->select('id')
                        ->from('data_lineage_records')
                        ->where('parent_record_id', $recordId)
                        ->when($tenantId, fn($qb) => $qb->where('tenant_id', $tenantId));
                });
            })
            ->when($tenantId, fn($q) => $q->byTenant($tenantId))
            ->with('actor:id,name,email')
            ->orderBy('recorded_at')
            ->get();

        $chain = collect(array_merge(
            array_reverse($ancestors),
            [$record],
            $descendants->toArray()
        ));

        return [
            'record' => $record,
            'ancestors' => $ancestors,
            'descendants' => $descendants,
            'chain' => array_values($chain->toArray()),
            'graph' => $this->buildGraph($chain->toArray()),
        ];
    }

    /**
     * 获取可追踪对象列表（聚合）
     */
    public function getTrackedObjects(int $tenantId, array $filters = []): array
    {
        $query = DataLineageRecord::byTenant($tenantId)
            ->select(
                'trackable_type',
                'trackable_id',
                'trackable_label',
                'data_category',
                'sensitivity',
                DB::raw('COUNT(*) as event_count'),
                DB::raw('MAX(recorded_at) as last_event_at'),
                DB::raw('MIN(recorded_at) as first_event_at')
            )
            ->groupBy('trackable_type', 'trackable_id', 'trackable_label', 'data_category', 'sensitivity');

        if ($type = $filters['trackable_type'] ?? null) {
            $query->where('trackable_type', $type);
        }
        if ($category = $filters['data_category'] ?? null) {
            $query->where('data_category', $category);
        }

        $perPage = $filters['per_page'] ?? 20;
        $page = $filters['page'] ?? 1;

        $results = $query->orderByDesc('last_event_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $results->items(),
            'pagination' => [
                'page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'last_page' => $results->lastPage(),
            ],
        ];
    }
}

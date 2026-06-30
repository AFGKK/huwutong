<?php

namespace App\Services;

use App\Models\FeatureGroup;
use App\Models\FeatureDefinition;
use App\Models\FeatureValue;
use App\Models\FeatureOfflineStore;
use App\Models\FeatureConsistencyCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * AI 特征工程平台服务 (M3-41)
 *
 * 特征商店 + 在线/离线一致性
 */
class FeatureStoreService
{
    // ═══════ 特征组管理 ═══════

    /**
     * 获取特征组列表
     */
    public function listGroups(int $tenantId, array $filters = []): array
    {
        $query = FeatureGroup::where('tenant_id', $tenantId)->withCount('features');

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('group_key', 'like', "%{$filters['search']}%");
            });
        }

        $perPage = $filters['per_page'] ?? 20;
        $results = $query->orderByDesc('id')->paginate($perPage)->withQueryString();
        return $results->toArray();
    }

    /**
     * 创建特征组
     */
    public function createGroup(int $tenantId, array $data): FeatureGroup
    {
        $data['tenant_id'] = $tenantId;
        $data['group_key'] = $data['group_key'] ?? Str::slug($data['name']) . '-' . Str::random(6);
        return FeatureGroup::create($data);
    }

    /**
     * 更新特征组
     */
    public function updateGroup(FeatureGroup $group, array $data): FeatureGroup
    {
        $group->update($data);
        return $group->fresh();
    }

    /**
     * 获取特征组详情
     */
    public function getGroup(FeatureGroup $group): array
    {
        $group->load('features');
        return [
            'group' => $group,
            'feature_count' => $group->features->count(),
            'online_count' => $group->features->where('is_online', true)->count(),
            'offline_count' => $group->features->where('is_offline', true)->count(),
            'recent_checks' => FeatureConsistencyCheck::whereIn('feature_definition_id', $group->features->pluck('id'))
                ->orderByDesc('checked_at')->take(10)->get(),
        ];
    }

    // ═══════ 特征定义管理 ═══════

    /**
     * 获取特征列表
     */
    public function listFeatures(int $groupId, array $filters = []): array
    {
        $query = FeatureDefinition::where('feature_group_id', $groupId);

        if (!empty($filters['value_type'])) {
            $query->where('value_type', $filters['value_type']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('feature_key', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('id')->get()->toArray();
    }

    /**
     * 创建特征定义
     */
    public function createFeature(int $groupId, array $data): FeatureDefinition
    {
        $data['feature_group_id'] = $groupId;
        return FeatureDefinition::create($data);
    }

    /**
     * 批量创建特征
     */
    public function batchCreateFeatures(int $groupId, array $features): array
    {
        $created = [];
        DB::transaction(function () use ($groupId, $features, &$created) {
            foreach ($features as $data) {
                $data['feature_group_id'] = $groupId;
                $created[] = FeatureDefinition::create($data);
            }
        });
        return $created;
    }

    /**
     * 更新特征定义
     */
    public function updateFeature(FeatureDefinition $feature, array $data): FeatureDefinition
    {
        $data['version'] = $feature->version + 1;
        $feature->update($data);
        return $feature->fresh();
    }

    // ═══════ 在线特征值 ═══════

    /**
     * 设置在线特征值
     */
    public function setOnlineFeature(int $featureDefinitionId, string $entityId, $value, ?int $ttl = null): FeatureValue
    {
        $definition = FeatureDefinition::findOrFail($featureDefinitionId);

        $record = FeatureValue::updateOrCreate(
            ['feature_definition_id' => $featureDefinitionId, 'entity_id' => $entityId],
            [
                'value' => is_string($value) ? $value : json_encode($value),
                'value_hash' => md5(is_string($value) ? $value : json_encode($value)),
                'effective_at' => now(),
                'expires_at' => $ttl ? now()->addSeconds($ttl) : now()->addSeconds(config('feature-store.features.default_ttl', 3600)),
            ]
        );

        // 同步到缓存
        $this->cacheFeatureValue($definition, $entityId, $value);

        return $record;
    }

    /**
     * 批量设置在线特征值
     */
    public function batchSetOnlineFeatures(int $featureDefinitionId, array $values): int
    {
        $definition = FeatureDefinition::findOrFail($featureDefinitionId);
        $count = 0;

        DB::transaction(function () use ($featureDefinitionId, $values, $definition, &$count) {
            foreach ($values as $entityId => $value) {
                FeatureValue::updateOrCreate(
                    ['feature_definition_id' => $featureDefinitionId, 'entity_id' => $entityId],
                    [
                        'value' => is_string($value) ? $value : json_encode($value),
                        'value_hash' => md5(is_string($value) ? $value : json_encode($value)),
                        'effective_at' => now(),
                    ]
                );
                $this->cacheFeatureValue($definition, $entityId, $value);
                $count++;
            }
        });

        return $count;
    }

    /**
     * 获取在线特征值（含缓存）
     */
    public function getOnlineFeature(int $featureDefinitionId, string $entityId): ?string
    {
        $definition = FeatureDefinition::findOrFail($featureDefinitionId);
        $cacheKey = "feature:{$definition->feature_key}:{$entityId}";

        // 尝试从缓存获取
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // 从数据库获取
        $record = FeatureValue::where('feature_definition_id', $featureDefinitionId)
            ->where('entity_id', $entityId)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($record) {
            Cache::put($cacheKey, $record->value, now()->addSeconds(config('feature-store.features.default_ttl', 3600)));
            return $record->value;
        }

        return $definition->default_value;
    }

    /**
     * 批量获取特征向量（用于模型推理）
     */
    public function getFeatureVector(string $entityType, string $entityId, array $featureKeys = []): array
    {
        $query = FeatureDefinition::whereHas('group', function ($q) use ($entityType) {
            $q->where('entity_type', $entityType);
        })->where('is_online', true);

        if (!empty($featureKeys)) {
            $query->whereIn('feature_key', $featureKeys);
        }

        $features = $query->get();
        $vector = [];

        foreach ($features as $feature) {
            $value = $this->getOnlineFeature($feature->id, $entityId);
            $vector[$feature->feature_key] = $this->castValue($value, $feature->value_type);
        }

        return $vector;
    }

    protected function cacheFeatureValue(FeatureDefinition $definition, string $entityId, $value): void
    {
        $cacheKey = "feature:{$definition->feature_key}:{$entityId}";
        Cache::put($cacheKey, is_string($value) ? $value : json_encode($value), now()->addSeconds(config('feature-store.features.default_ttl', 3600)));
    }

    protected function castValue(?string $value, string $type): mixed
    {
        if ($value === null) return null;
        return match ($type) {
            'int' => (int) $value,
            'float', 'double' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            'vector' => json_decode($value, true),
            default => $value,
        };
    }

    // ═══════ 离线特征存储 ═══════

    /**
     * 同步在线特征到离线存储
     */
    public function syncOnlineToOffline(int $featureDefinitionId, ?string $entityId = null): int
    {
        $query = FeatureValue::where('feature_definition_id', $featureDefinitionId);
        if ($entityId) {
            $query->where('entity_id', $entityId);
        }

        $count = 0;
        $query->chunk(100, function ($records) use ($featureDefinitionId, &$count) {
            foreach ($records as $record) {
                FeatureOfflineStore::updateOrCreate(
                    [
                        'feature_definition_id' => $featureDefinitionId,
                        'entity_id' => $record->entity_id,
                        'event_date' => today(),
                    ],
                    [
                        'value' => $record->value,
                        'value_hash' => $record->value_hash,
                        'batch_processed_at' => now(),
                    ]
                );
                $count++;
            }
        });

        return $count;
    }

    /**
     * 同步所有在线特征到离线
     */
    public function syncAllToOffline(?int $tenantId = null): array
    {
        $results = ['synced' => 0, 'failed' => 0];

        $query = FeatureDefinition::where('is_offline', true);
        if ($tenantId) {
            $query->whereHas('group', fn($q) => $q->where('tenant_id', $tenantId));
        }

        $query->chunk(50, function ($features) use (&$results) {
            foreach ($features as $feature) {
                try {
                    $count = $this->syncOnlineToOffline($feature->id);
                    $results['synced'] += $count;
                } catch (\Throwable $e) {
                    $results['failed']++;
                    Log::error('离线同步失败', ['feature_id' => $feature->id, 'error' => $e->getMessage()]);
                }
            }
        });

        return $results;
    }

    /**
     * 获取离线特征数据（用于训练）
     */
    public function getOfflineTrainingData(int $featureDefinitionId, string $startDate, string $endDate): array
    {
        return FeatureOfflineStore::where('feature_definition_id', $featureDefinitionId)
            ->whereBetween('event_date', [$startDate, $endDate])
            ->orderBy('event_date')
            ->get(['entity_id', 'value', 'event_date'])
            ->toArray();
    }

    // ═══════ 一致性检查 ═══════

    /**
     * 执行在线/离线一致性检查
     */
    public function checkConsistency(int $featureDefinitionId, ?int $sampleSize = null): FeatureConsistencyCheck
    {
        $definition = FeatureDefinition::findOrFail($featureDefinitionId);
        $sampleSize = $sampleSize ?? config('feature-store.consistency.sample_size', 1000);

        // 获取在线数据
        $onlineData = FeatureValue::where('feature_definition_id', $featureDefinitionId)
            ->whereNotNull('value_hash')
            ->inRandomOrder()
            ->take($sampleSize)
            ->get()
            ->keyBy('entity_id');

        if ($onlineData->isEmpty()) {
            return FeatureConsistencyCheck::create([
                'feature_definition_id' => $featureDefinitionId,
                'total_samples' => 0,
                'matched_count' => 0,
                'mismatched_count' => 0,
                'match_percent' => 100,
                'drift_percent' => 0,
                'status' => 'passed',
                'details' => ['note' => '无在线数据'],
                'checked_at' => now(),
            ]);
        }

        // 获取离线数据
        $entityIds = $onlineData->keys()->toArray();
        $offlineData = FeatureOfflineStore::where('feature_definition_id', $featureDefinitionId)
            ->whereIn('entity_id', $entityIds)
            ->where('event_date', today())
            ->get()
            ->keyBy('entity_id');

        $matched = 0;
        $mismatched = 0;
        $details = [];

        foreach ($onlineData as $entityId => $onlineRecord) {
            $offlineRecord = $offlineData->get($entityId);

            if (!$offlineRecord) {
                $mismatched++;
                if (count($details) < 10) {
                    $details[] = [
                        'entity_id' => $entityId,
                        'issue' => 'offline_missing',
                        'online_value_hash' => $onlineRecord->value_hash,
                    ];
                }
                continue;
            }

            if ($onlineRecord->value_hash === $offlineRecord->value_hash) {
                $matched++;
            } else {
                $mismatched++;
                if (count($details) < 10) {
                    $details[] = [
                        'entity_id' => $entityId,
                        'issue' => 'value_mismatch',
                        'online_hash' => $onlineRecord->value_hash,
                        'offline_hash' => $offlineRecord->value_hash,
                    ];
                }
            }
        }

        $total = $matched + $mismatched;
        $matchPercent = $total > 0 ? round(($matched / $total) * 100, 2) : 100;
        $driftPercent = round(100 - $matchPercent, 2);

        $maxDrift = config('feature-store.consistency.max_drift_percent', 5.0);
        $status = $driftPercent <= $maxDrift ? 'passed' : ($driftPercent <= $maxDrift * 2 ? 'warning' : 'failed');

        $check = FeatureConsistencyCheck::create([
            'feature_definition_id' => $featureDefinitionId,
            'total_samples' => $total,
            'matched_count' => $matched,
            'mismatched_count' => $mismatched,
            'match_percent' => $matchPercent,
            'drift_percent' => $driftPercent,
            'status' => $status,
            'details' => $details,
            'checked_at' => now(),
        ]);

        // 漂移告警
        if ($status === 'failed' && config('feature-store.consistency.alert_on_drift')) {
            Log::warning('特征一致性检查失败', [
                'feature_definition_id' => $featureDefinitionId,
                'feature' => $definition->feature_key,
                'drift_percent' => $driftPercent,
                'mismatched' => $mismatched,
            ]);
        }

        return $check;
    }

    /**
     * 批量一致性检查
     */
    public function batchCheckConsistency(?int $tenantId = null): array
    {
        $results = ['checked' => 0, 'passed' => 0, 'warning' => 0, 'failed' => 0];

        $query = FeatureDefinition::where('is_online', true)->where('is_offline', true);
        if ($tenantId) {
            $query->whereHas('group', fn($q) => $q->where('tenant_id', $tenantId));
        }

        $query->chunk(50, function ($features) use (&$results) {
            foreach ($features as $feature) {
                try {
                    $check = $this->checkConsistency($feature->id, 100);
                    $results['checked']++;
                    $results[$check->status]++;
                } catch (\Throwable $e) {
                    Log::error('批量一致性检查失败', ['feature_id' => $feature->id, 'error' => $e->getMessage()]);
                }
            }
        });

        return $results;
    }

    // ═══════ 仪表盘 ═══════

    /**
     * 获取特征工程仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        $groups = FeatureGroup::where('tenant_id', $tenantId)
            ->withCount(['features', 'features as online_count' => fn($q) => $q->where('is_online', true),
                'features as offline_count' => fn($q) => $q->where('is_offline', true)])
            ->get();

        $totalFeatures = $groups->sum('features_count');
        $onlineFeatures = $groups->sum('online_count');
        $offlineFeatures = $groups->sum('offline_count');

        $recentChecks = FeatureConsistencyCheck::whereIn('feature_definition_id', function ($q) use ($tenantId) {
            $q->select('id')->from('feature_definitions')
              ->whereHas('group', fn($sq) => $sq->where('tenant_id', $tenantId));
        })->orderByDesc('checked_at')->take(10)->get();

        $checkSummary = [
            'total' => $recentChecks->count(),
            'passed' => $recentChecks->where('status', 'passed')->count(),
            'warning' => $recentChecks->where('status', 'warning')->count(),
            'failed' => $recentChecks->where('status', 'failed')->count(),
        ];

        return [
            'total_groups' => $groups->count(),
            'total_features' => $totalFeatures,
            'online_features' => $onlineFeatures,
            'offline_features' => $offlineFeatures,
            'by_entity_type' => $groups->groupBy('entity_type')->map(fn($g) => $g->count())->toArray(),
            'by_source_type' => $groups->groupBy('source_type')->map(fn($g) => $g->count())->toArray(),
            'recent_checks' => $recentChecks,
            'check_summary' => $checkSummary,
            'recent_groups' => $groups->sortByDesc('id')->take(5)->values(),
        ];
    }
}

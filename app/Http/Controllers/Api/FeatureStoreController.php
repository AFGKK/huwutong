<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\FeatureGroup;
use App\Models\FeatureDefinition;
use App\Services\FeatureStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI 特征工程平台控制器 (M3-41)
 *
 * 特征商店 + 在线/离线一致性
 */
class FeatureStoreController extends Controller
{
    public function __construct(
        protected FeatureStoreService $featureStore,
    ) {}

    /**
     * 仪表盘
     *
     * GET /api/admin/feature-store/dashboard
     */
    public function dashboard(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        return ApiResponse::success($this->featureStore->getDashboard($tenantId));
    }

    // ═══════ 特征组 ═══════

    /**
     * 特征组列表
     *
     * GET /api/admin/feature-store/groups
     */
    public function groups(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $filters = $request->only(['entity_type', 'status', 'search']);
        return ApiResponse::success($this->featureStore->listGroups($tenantId, $filters));
    }

    /**
     * 创建特征组
     *
     * POST /api/admin/feature-store/groups
     */
    public function storeGroup(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'group_key' => 'nullable|string|max:100|unique:feature_groups,group_key',
            'entity_type' => 'required|string|max:100',
            'description' => 'nullable|string|max:2000',
            'source_type' => 'nullable|in:manual,sql_query,api_endpoint,kafka_topic,file_upload,model_output',
            'source_config' => 'nullable|array',
            'tags' => 'nullable|array',
        ]);

        $group = $this->featureStore->createGroup($tenantId, $validated);
        return ApiResponse::created($group, __('app.api.feature_store.group_created'));
    }

    /**
     * 特征组详情
     *
     * GET /api/admin/feature-store/groups/{group}
     */
    public function showGroup(FeatureGroup $group): JsonResponse
    {
        return ApiResponse::success($this->featureStore->getGroup($group));
    }

    /**
     * 更新特征组
     *
     * PUT /api/admin/feature-store/groups/{group}
     */
    public function updateGroup(Request $request, FeatureGroup $group): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:200',
            'description' => 'nullable|string|max:2000',
            'status' => 'in:active,inactive,deprecated',
            'source_type' => 'nullable|in:manual,sql_query,api_endpoint,kafka_topic,file_upload,model_output',
            'source_config' => 'nullable|array',
            'tags' => 'nullable|array',
        ]);

        $group = $this->featureStore->updateGroup($group, $validated);
        return ApiResponse::success($group, __('app.api.feature_store.group_updated'));
    }

    /**
     * 删除特征组
     *
     * DELETE /api/admin/feature-store/groups/{group}
     */
    public function destroyGroup(FeatureGroup $group): JsonResponse
    {
        $group->delete();
        return ApiResponse::success(null, __('app.api.feature_store.group_deleted'));
    }

    // ═══════ 特征定义 ═══════

    /**
     * 特征列表
     *
     * GET /api/admin/feature-store/groups/{group}/features
     */
    public function features(Request $request, FeatureGroup $group): JsonResponse
    {
        $filters = $request->only(['value_type', 'search']);
        return ApiResponse::success($this->featureStore->listFeatures($group->id, $filters));
    }

    /**
     * 创建特征
     *
     * POST /api/admin/feature-store/groups/{group}/features
     */
    public function storeFeature(Request $request, FeatureGroup $group): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'feature_key' => 'required|string|max:100',
            'value_type' => 'required|in:int,float,double,string,boolean,json,vector',
            'description' => 'nullable|string|max:2000',
            'is_online' => 'boolean',
            'is_offline' => 'boolean',
            'default_value' => 'nullable|string',
            'validation_rules' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);

        $feature = $this->featureStore->createFeature($group->id, $validated);
        return ApiResponse::created($feature, __('app.api.feature_store.feature_created'));
    }

    /**
     * 批量创建特征
     *
     * POST /api/admin/feature-store/groups/{group}/features/batch
     */
    public function batchStoreFeatures(Request $request, FeatureGroup $group): JsonResponse
    {
        $validated = $request->validate([
            'features' => 'required|array|min:1|max:100',
            'features.*.name' => 'required|string|max:200',
            'features.*.feature_key' => 'required|string|max:100',
            'features.*.value_type' => 'required|in:int,float,double,string,boolean,json,vector',
            'features.*.description' => 'nullable|string|max:2000',
        ]);

        $created = $this->featureStore->batchCreateFeatures($group->id, $validated['features']);
        return ApiResponse::created($created, __('app.api.feature_store.features_created', ['count' => count($created)]));
    }

    /**
     * 更新特征
     *
     * PUT /api/admin/feature-store/features/{feature}
     */
    public function updateFeature(Request $request, FeatureDefinition $feature): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:200',
            'description' => 'nullable|string|max:2000',
            'is_online' => 'boolean',
            'is_offline' => 'boolean',
            'default_value' => 'nullable|string',
            'validation_rules' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);

        $feature = $this->featureStore->updateFeature($feature, $validated);
        return ApiResponse::success($feature, __('app.api.feature_store.feature_updated'));
    }

    /**
     * 删除特征
     *
     * DELETE /api/admin/feature-store/features/{feature}
     */
    public function destroyFeature(FeatureDefinition $feature): JsonResponse
    {
        $feature->delete();
        return ApiResponse::success(null, __('app.api.feature_store.feature_deleted'));
    }

    // ═══════ 在线特征值 ═══════

    /**
     * 设置在线特征值
     *
     * POST /api/admin/feature-store/features/{feature}/values
     */
    public function setValue(Request $request, FeatureDefinition $feature): JsonResponse
    {
        $validated = $request->validate([
            'entity_id' => 'required|string|max:100',
            'value' => 'required',
            'ttl' => 'nullable|integer|min:60|max:86400',
        ]);

        $record = $this->featureStore->setOnlineFeature(
            $feature->id,
            $validated['entity_id'],
            $validated['value'],
            $validated['ttl'] ?? null,
        );

        return ApiResponse::success($record, __('app.api.feature_store.value_set'));
    }

    /**
     * 批量设置特征值
     *
     * POST /api/admin/feature-store/features/{feature}/values/batch
     */
    public function batchSetValues(Request $request, FeatureDefinition $feature): JsonResponse
    {
        $validated = $request->validate([
            'values' => 'required|array|min:1|max:1000',
            'values.*.entity_id' => 'required|string|max:100',
            'values.*.value' => 'required',
        ]);

        $values = [];
        foreach ($validated['values'] as $item) {
            $values[$item['entity_id']] = $item['value'];
        }

        $count = $this->featureStore->batchSetOnlineFeatures($feature->id, $values);
        return ApiResponse::success(null, __('app.api.feature_store.batch_values_set', ['count' => $count]));
    }

    /**
     * 获取特征值
     *
     * GET /api/admin/feature-store/features/{feature}/values/{entityId}
     */
    public function getValue(FeatureDefinition $feature, string $entityId): JsonResponse
    {
        $value = $this->featureStore->getOnlineFeature($feature->id, $entityId);
        return ApiResponse::success([
            'feature_key' => $feature->feature_key,
            'entity_id' => $entityId,
            'value' => $value,
            'value_type' => $feature->value_type,
        ]);
    }

    /**
     * 获取特征向量
     *
     * POST /api/admin/feature-store/feature-vector
     */
    public function getFeatureVector(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => 'required|string|max:100',
            'entity_id' => 'required|string|max:100',
            'feature_keys' => 'nullable|array',
            'feature_keys.*' => 'string|max:100',
        ]);

        $vector = $this->featureStore->getFeatureVector(
            $validated['entity_type'],
            $validated['entity_id'],
            $validated['feature_keys'] ?? [],
        );

        return ApiResponse::success($vector);
    }

    // ═══════ 离线同步 ═══════

    /**
     * 同步在线到离线
     *
     * POST /api/admin/feature-store/features/{feature}/sync-offline
     */
    public function syncOffline(Request $request, FeatureDefinition $feature): JsonResponse
    {
        $entityId = $request->input('entity_id');
        $count = $this->featureStore->syncOnlineToOffline($feature->id, $entityId);
        return ApiResponse::success(['synced_count' => $count], __('app.api.feature_store.synced', ['count' => $count]));
    }

    /**
     * 同步所有到离线
     *
     * POST /api/admin/feature-store/sync-all-offline
     */
    public function syncAllOffline(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $results = $this->featureStore->syncAllToOffline($tenantId);
        return ApiResponse::success($results, __('app.api.feature_store.sync_done', ['synced' => $results['synced'], 'failed' => $results['failed']]));
    }

    /**
     * 获取离线训练数据
     *
     * GET /api/admin/feature-store/features/{feature}/offline-training
     */
    public function offlineTraining(Request $request, FeatureDefinition $feature): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $data = $this->featureStore->getOfflineTrainingData($feature->id, $validated['start_date'], $validated['end_date']);
        return ApiResponse::success($data);
    }

    // ═══════ 一致性检查 ═══════

    /**
     * 执行一致性检查
     *
     * POST /api/admin/feature-store/features/{feature}/check-consistency
     */
    public function checkConsistency(Request $request, FeatureDefinition $feature): JsonResponse
    {
        $sampleSize = $request->input('sample_size');
        $check = $this->featureStore->checkConsistency($feature->id, $sampleSize);
        return ApiResponse::success($check, __('app.api.feature_store.consistency_done', ['percent' => $check->match_percent]));
    }

    /**
     * 批量一致性检查
     *
     * POST /api/admin/feature-store/batch-check-consistency
     */
    public function batchCheckConsistency(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $results = $this->featureStore->batchCheckConsistency($tenantId);
        return ApiResponse::success($results, __('app.api.feature_store.batch_check_done', ['count' => $results['checked']]));
    }

    /**
     * 一致性检查历史
     *
     * GET /api/admin/feature-store/consistency-history
     */
    public function consistencyHistory(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $perPage = $request->input('per_page', 20);

        $query = \App\Models\FeatureConsistencyCheck::with('definition.group')
            ->whereHas('definition.group', fn($q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('checked_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('feature_definition_id')) {
            $query->where('feature_definition_id', $request->input('feature_definition_id'));
        }

        return ApiResponse::paginated($query->paginate($perPage)->withQueryString());
    }
}

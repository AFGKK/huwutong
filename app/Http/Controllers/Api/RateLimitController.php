<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RateLimitRule;
use App\Services\EnhancedRateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 限流规则管理 API
 *
 * M2-92 API 精细化限流 — 规则配置/统计监控
 */
class RateLimitController extends Controller
{
    public function __construct(
        protected EnhancedRateLimiter $rateLimiter,
    ) {}

    /**
     * 规则列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = RateLimitRule::orderBy('priority', 'asc');

        if ($request->filled('key_type')) {
            $query->where('key_type', $request->input('key_type'));
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $rules = $query->paginate($request->input('per_page', 50));

        return response()->json(['success' => true, 'data' => $rules]);
    }

    /**
     * 创建规则
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:rate_limit_rules,slug',
            'key_type' => 'required|in:ip,license,product,tenant,api,global,api_key',
            'max_attempts' => 'required|integer|min:1|max:100000',
            'window_seconds' => 'required|integer|min:1|max:86400',
            'decay_ms' => 'nullable|integer|min:0',
            'priority' => 'integer|min:0|max:999',
            'description' => 'nullable|string|max:500',
            'conditions' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $rule = RateLimitRule::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => '限流规则已创建',
            'data' => $rule,
        ], 201);
    }

    /**
     * 更新规则
     */
    public function update(Request $request, RateLimitRule $rateLimitRule): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'key_type' => 'sometimes|in:ip,license,product,tenant,api,global,api_key',
            'max_attempts' => 'sometimes|integer|min:1|max:100000',
            'window_seconds' => 'sometimes|integer|min:1|max:86400',
            'decay_ms' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0|max:999',
            'description' => 'nullable|string|max:500',
            'conditions' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $rateLimitRule->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => '限流规则已更新',
            'data' => $rateLimitRule->fresh(),
        ]);
    }

    /**
     * 删除规则
     */
    public function destroy(RateLimitRule $rateLimitRule): JsonResponse
    {
        $rateLimitRule->delete();

        return response()->json([
            'success' => true,
            'message' => '限流规则已删除',
        ]);
    }

    /**
     * 限流统计
     */
    public function stats(Request $request): JsonResponse
    {
        $filters = $request->only(['rule_slug', 'dimension', 'from', 'to']);
        $stats = $this->rateLimiter->getStats($filters);

        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * 获取 key_type 选项列表
     */
    public function keyTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['id' => 'ip', 'name' => 'IP 地址'],
                ['id' => 'license', 'name' => 'License Key'],
                ['id' => 'product', 'name' => '产品'],
                ['id' => 'tenant', 'name' => '租户'],
                ['id' => 'api', 'name' => 'API 路径'],
                ['id' => 'api_key', 'name' => 'API Key'],
                ['id' => 'global', 'name' => '全局'],
            ],
        ]);
    }
}

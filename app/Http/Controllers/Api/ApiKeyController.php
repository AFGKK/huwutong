<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    /**
     * 获取当前用户的 API 密钥列表
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $keys = ApiKey::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($key) => [
                'id' => $key->id,
                'key_id' => $key->key_id,
                'name' => $key->name,
                'permissions' => $key->permissions,
                'allowed_endpoints' => $key->allowed_endpoints,
                'rate_limit' => $key->rate_limit,
                'usage_quota' => $key->usage_quota,
                'usage_count' => $key->usage_count,
                'allowed_ip' => $key->allowed_ip,
                'is_active' => $key->is_active,
                'last_used_at' => $key->last_used_at,
                'expires_at' => $key->expires_at,
                'created_at' => $key->created_at,
                'updated_at' => $key->updated_at,
            ]);

        return ApiResponse::success($keys);
    }

    /**
     * 创建新的 API 密钥
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'permissions' => 'sometimes|in:read-only,read-write,admin',
            'allowed_endpoints' => 'sometimes|nullable|array',
            'allowed_endpoints.*' => 'string',
            'rate_limit' => 'sometimes|nullable|integer|min:1|max:10000',
            'usage_quota' => 'sometimes|nullable|integer|min:1',
            'allowed_ip' => 'sometimes|nullable|ip',
            'expires_at' => 'sometimes|nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $tenantId = $request->user()->tenant_id;

        // 检查密钥数量限制
        $maxKeys = 20;
        $keyCount = ApiKey::where('tenant_id', $tenantId)->count();
        if ($keyCount >= $maxKeys) {
            return ApiResponse::error('MAX_KEYS_REACHED', "最多可创建 {$maxKeys} 个 API 密钥", 422);
        }

        $keyId = 'ak_' . Str::random(32);
        $secret = Str::random(48);

        $data = $validator->validated();
        $data['tenant_id'] = $tenantId;
        $data['key_id'] = $keyId;
        $data['secret'] = Hash::make($secret);
        $data['is_active'] = true;

        $key = ApiKey::create($data);

        return response()->json([
            'success' => true,
            'message' => 'API 密钥创建成功',
            'data' => [
                'id' => $key->id,
                'key_id' => $key->key_id,
                'name' => $key->name,
                'permissions' => $key->permissions,
                'allowed_endpoints' => $key->allowed_endpoints,
                'rate_limit' => $key->rate_limit,
                'usage_quota' => $key->usage_quota,
                'allowed_ip' => $key->allowed_ip,
                'secret' => $secret,
                'is_active' => $key->is_active,
                'expires_at' => $key->expires_at,
                'created_at' => $key->created_at,
            ],
        ], 201);
    }

    /**
     * 获取 API 密钥详情
     */
    public function show(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorizeKey($request, $apiKey);

        return ApiResponse::success([
            'id' => $apiKey->id,
            'key_id' => $apiKey->key_id,
            'name' => $apiKey->name,
            'permissions' => $apiKey->permissions,
            'allowed_endpoints' => $apiKey->allowed_endpoints,
            'rate_limit' => $apiKey->rate_limit,
            'usage_quota' => $apiKey->usage_quota,
            'usage_count' => $apiKey->usage_count,
            'allowed_ip' => $apiKey->allowed_ip,
            'is_active' => $apiKey->is_active,
            'last_used_at' => $apiKey->last_used_at,
            'expires_at' => $apiKey->expires_at,
            'created_at' => $apiKey->created_at,
            'updated_at' => $apiKey->updated_at,
        ]);
    }

    /**
     * 更新 API 密钥
     */
    public function update(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorizeKey($request, $apiKey);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'permissions' => 'sometimes|in:read-only,read-write,admin',
            'allowed_endpoints' => 'sometimes|nullable|array',
            'allowed_endpoints.*' => 'string',
            'rate_limit' => 'sometimes|nullable|integer|min:1|max:10000',
            'usage_quota' => 'sometimes|nullable|integer|min:1',
            'allowed_ip' => 'sometimes|nullable|ip',
            'expires_at' => 'sometimes|nullable|date',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $apiKey->update($validator->validated());

        return ApiResponse::success($apiKey->fresh(), 'API 密钥已更新');
    }

    /**
     * 删除 API 密钥
     */
    public function destroy(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorizeKey($request, $apiKey);

        $apiKey->delete();

        return ApiResponse::success(null, 'API 密钥已删除');
    }

    /**
     * 重新生成密钥
     */
    public function regenerate(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorizeKey($request, $apiKey);

        $newSecret = Str::random(48);
        $apiKey->secret = Hash::make($newSecret);
        $apiKey->save();

        return ApiResponse::success([
            'id' => $apiKey->id,
            'key_id' => $apiKey->key_id,
            'secret' => $newSecret,
        ], '密钥已重新生成');
    }

    /**
     * 验证当前用户是否拥有此 API Key
     */
    protected function authorizeKey(Request $request, ApiKey $apiKey): void
    {
        $tenantId = $request->user()->tenant_id;

        if ($apiKey->tenant_id !== $tenantId) {
            abort(403, '无权操作此 API 密钥');
        }
    }
}

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
     *
     * GET /api/api-keys
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
     *
     * POST /api/api-keys
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'expires_at' => 'sometimes|nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $tenantId = $request->user()->tenant_id;

        // 检查密钥数量限制
        $maxKeys = 10;
        $keyCount = ApiKey::where('tenant_id', $tenantId)->count();
        if ($keyCount >= $maxKeys) {
            return ApiResponse::error('MAX_KEYS_REACHED', "最多可创建 {$maxKeys} 个 API 密钥", 422);
        }

        $keyId = 'ak_' . Str::random(32);
        $secret = Str::random(48);

        $key = ApiKey::create([
            'tenant_id' => $tenantId,
            'key_id' => $keyId,
            'name' => $request->input('name'),
            'secret' => Hash::make($secret),
            'is_active' => true,
            'expires_at' => $request->input('expires_at'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API 密钥创建成功',
            'data' => [
                'id' => $key->id,
                'key_id' => $key->key_id,
                'name' => $key->name,
                'secret' => $secret,
                'is_active' => $key->is_active,
                'expires_at' => $key->expires_at,
                'created_at' => $key->created_at,
            ],
        ], 201);
    }

    /**
     * 获取 API 密钥详情
     *
     * GET /api/api-keys/{apiKey}
     */
    public function show(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorizeKey($request, $apiKey);

        return ApiResponse::success([
            'id' => $apiKey->id,
            'key_id' => $apiKey->key_id,
            'name' => $apiKey->name,
            'is_active' => $apiKey->is_active,
            'last_used_at' => $apiKey->last_used_at,
            'expires_at' => $apiKey->expires_at,
            'created_at' => $apiKey->created_at,
            'updated_at' => $apiKey->updated_at,
        ]);
    }

    /**
     * 更新 API 密钥（名称、过期时间）
     *
     * PUT /api/api-keys/{apiKey}
     */
    public function update(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorizeKey($request, $apiKey);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'expires_at' => 'sometimes|nullable|date|after:today',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        if ($request->has('name')) {
            $apiKey->name = $request->input('name');
        }
        if ($request->has('expires_at')) {
            $apiKey->expires_at = $request->input('expires_at');
        }
        if ($request->has('is_active')) {
            $apiKey->is_active = $request->boolean('is_active');
        }

        $apiKey->save();

        return ApiResponse::success([
            'id' => $apiKey->id,
            'key_id' => $apiKey->key_id,
            'name' => $apiKey->name,
            'is_active' => $apiKey->is_active,
            'last_used_at' => $apiKey->last_used_at,
            'expires_at' => $apiKey->expires_at,
            'created_at' => $apiKey->created_at,
            'updated_at' => $apiKey->updated_at,
        ], 'API 密钥已更新');
    }

    /**
     * 删除 API 密钥
     *
     * DELETE /api/api-keys/{apiKey}
     */
    public function destroy(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorizeKey($request, $apiKey);

        $apiKey->delete();

        return ApiResponse::success(null, 'API 密钥已删除');
    }

    /**
     * 重新生成密钥（更换 secret）
     *
     * POST /api/api-keys/{apiKey}/regenerate
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

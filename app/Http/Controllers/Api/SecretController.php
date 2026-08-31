<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterKey;
use App\Models\SecretAccessLog;
use App\Models\TenantSecret;
use App\Services\SecretManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 密钥管理器 API
 */
class SecretController extends Controller
{
    public function __construct(
        protected SecretManager $secretManager,
    ) {}

    /**
     * 凭据列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = TenantSecret::with('tenant:id,name')
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }

        $secrets = $query->paginate($request->input('per_page', 20));

        // 永远不返回 encrypted_value
        $secrets->getCollection()->transform(function ($s) {
            return [
                'id' => $s->id,
                'tenant_id' => $s->tenant_id,
                'name' => $s->name,
                'slug' => $s->slug,
                'type' => $s->type,
                'description' => $s->description,
                'status' => $s->status,
                'expires_at' => $s->expires_at?->toIso8601String(),
                'last_used_at' => $s->last_used_at?->toIso8601String(),
                'last_rotated_by' => $s->last_rotated_by,
                'created_at' => $s->created_at->toIso8601String(),
            ];
        });

        return response()->json(['success' => true, 'data' => $secrets]);
    }

    /**
     * 创建凭据
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100',
            'value' => 'required|string',
            'type' => 'sometimes|in:api_key,password,certificate,token,connection',
            'description' => 'sometimes|nullable|string|max:500',
            'expires_at' => 'sometimes|nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            // 检查 slug 是否已存在
            $exists = TenantSecret::where('tenant_id', $data['tenant_id'])
                ->where('slug', $data['slug'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'errors' => ['slug' => [__('app.api.secret.slug_exists')]],
                ], 422);
            }

            $secret = $this->secretManager->createSecret(
                $data['tenant_id'],
                $data['name'],
                $data['slug'],
                $data['value'],
                [
                    'type' => $data['type'] ?? 'api_key',
                    'description' => $data['description'] ?? null,
                    'expires_at' => $data['expires_at'] ?? now()->addDays(
                        config('secret-manager.default_expiry_days', 730)
                    ),
                    'rotated_by' => $request->user()?->id,
                    'accessed_by' => 'user:' . $request->user()?->id,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => __('app.api.secret.created'),
                'data' => ['id' => $secret->id, 'name' => $secret->name, 'slug' => $secret->slug],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('app.api.secret.create_failed', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * 获取凭据明文（需要二次确认）
     */
    public function show(TenantSecret $tenantSecret, Request $request): JsonResponse
    {
        // 安全验证：需要二次确认密码或 MFA
        if (!$request->boolean('confirm')) {
            return response()->json([
                'success' => false,
                'message' => __('app.api.secret.confirm_required'),
            ], 403);
        }

        try {
            $value = $this->secretManager->getSecret($tenantSecret);

            if ($value === null) {
                return response()->json([
                    'success' => false,
                    'message' => __('app.api.secret.unavailable'),
                ], 404);
            }

            // 记录访问审计
            SecretAccessLog::create([
                'secret_id' => $tenantSecret->id,
                'tenant_id' => $tenantSecret->tenant_id,
                'action' => 'access',
                'accessed_by' => 'user:' . $request->user()?->id,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $tenantSecret->id,
                    'slug' => $tenantSecret->slug,
                    'value' => $value,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('app.api.secret.decrypt_failed'),
            ], 500);
        }
    }

    /**
     * 轮换凭据
     */
    public function rotate(Request $request, TenantSecret $tenantSecret): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'value' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $this->secretManager->rotateSecret(
                $tenantSecret,
                $request->input('value'),
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => __('app.api.secret.rotated'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('app.api.secret.rotate_failed', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * 吊销凭据
     */
    public function revoke(TenantSecret $tenantSecret, Request $request): JsonResponse
    {
        try {
            $this->secretManager->revokeSecret($tenantSecret, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => __('app.api.secret.revoked'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('app.api.secret.revoke_failed', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * 恢复凭据
     */
    public function restore(TenantSecret $tenantSecret, Request $request): JsonResponse
    {
        try {
            $this->secretManager->restoreSecret($tenantSecret, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => __('app.api.secret.restored'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 审计日志
     */
    public function accessLogs(Request $request, ?TenantSecret $tenantSecret = null): JsonResponse
    {
        $query = SecretAccessLog::with('secret:id,name,slug')
            ->orderBy('created_at', 'desc');

        if ($tenantSecret) {
            $query->where('secret_id', $tenantSecret->id);
        }
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }

        $logs = $query->paginate($request->input('per_page', 30));

        return response()->json(['success' => true, 'data' => $logs]);
    }

    /**
     * 主密钥管理
     */
    public function masterKeys(): JsonResponse
    {
        $keys = MasterKey::orderBy('created_at', 'desc')->get()->map(function ($k) {
            return [
                'id' => $k->id,
                'key_id' => $k->key_id,
                'label' => $k->label,
                'algorithm' => $k->algorithm,
                'status' => $k->status,
                'is_current' => $k->is_current,
                'rotated_at' => $k->rotated_at?->toIso8601String(),
                'expires_at' => $k->expires_at?->toIso8601String(),
                'created_at' => $k->created_at->toIso8601String(),
            ];
        });

        return response()->json(['success' => true, 'data' => $keys]);
    }

    /**
     * 生成新主密钥
     */
    public function generateMasterKey(Request $request): JsonResponse
    {
        try {
            $result = $this->secretManager->generateMasterKey(
                $request->input('label', 'Manual generation')
            );

            return response()->json([
                'success' => true,
                'message' => __('app.api.secret.master_generated'),
                'data' => ['key_id' => $result->key_id],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('app.api.secret.generate_failed', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * 轮换主密钥
     */
    public function rotateMasterKey(): JsonResponse
    {
        try {
            $result = $this->secretManager->rotateMasterKey();

            return response()->json([
                'success' => true,
                'message' => __('app.api.secret.master_rotated', ['count' => $result['re_encrypted_secrets']]),
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('app.api.secret.rotate_failed', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * 健康状态
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->secretManager->health(),
        ]);
    }

    /**
     * 获取凭据类型列表
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['id' => 'api_key', 'name' => 'API Key'],
                ['id' => 'password', 'name' => __('app.api.secret.type_password')],
                ['id' => 'certificate', 'name' => __('app.api.secret.type_certificate')],
                ['id' => 'token', 'name' => __('app.api.secret.type_token')],
                ['id' => 'connection', 'name' => __('app.api.secret.type_connection')],
            ],
        ]);
    }
}

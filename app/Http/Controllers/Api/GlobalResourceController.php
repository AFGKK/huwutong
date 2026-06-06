<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Middleware\GlobalResourceWhitelist;
use App\Models\GlobalResourceOperation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalResourceController extends Controller
{
    /**
     * 获取白名单配置状态
     */
    public function config(): JsonResponse
    {
        return ApiResponse::success([
            'whitelisted_models' => GlobalResourceWhitelist::getWhitelistedModels(),
            'whitelisted_tables' => GlobalResourceWhitelist::getWhitelistedTables(),
            'write_roles' => config('global-resources.write_roles', []),
        ]);
    }

    /**
     * 写入保护检查
     */
    public function checkWrite(Request $request): JsonResponse
    {
        $user = $request->user();
        $canWrite = GlobalResourceWhitelist::canWrite();

        return ApiResponse::success([
            'can_write' => $canWrite,
            'user_role' => $user?->getRoleNames()?->first(),
        ]);
    }

    /**
     * 操作审计日志
     */
    public function operations(Request $request): JsonResponse
    {
        $query = GlobalResourceOperation::orderBy('created_at', 'desc');

        if ($type = $request->input('resource_type')) {
            $query->where('resource_type', 'like', "%{$type}%");
        }

        if ($request->has('allowed')) {
            $query->where('allowed', $request->boolean('allowed'));
        }

        $ops = $query->paginate($request->input('per_page', 30));

        $ops->getCollection()->transform(function ($o) {
            return [
                'id' => $o->id,
                'operation' => $o->operation,
                'resource_type' => $o->resource_type,
                'resource_id' => $o->resource_id,
                'user_id' => $o->user_id,
                'user_role' => $o->user_role,
                'payload' => $o->payload,
                'ip_address' => $o->ip_address,
                'allowed' => $o->allowed,
                'reason' => $o->reason,
                'created_at' => $o->created_at?->toIso8601String(),
            ];
        });

        return ApiResponse::success($ops);
    }

    /**
     * 记录白名单资源操作
     */
    public static function recordOperation(
        string $operation,
        string $resourceType,
        ?int $resourceId = null,
        ?array $payload = null,
        bool $allowed = true,
        ?string $reason = null,
    ): void {
        try {
            $user = auth()->user();

            GlobalResourceOperation::create([
                'operation' => $operation,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'user_id' => $user?->id,
                'user_role' => $user?->getRoleNames()?->first(),
                'payload' => $payload,
                'ip_address' => request()->ip(),
                'allowed' => $allowed,
                'reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('记录全局资源操作失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 白名单检查 — 用于 ProductController 等
     */
    public function verifyAccess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model_class' => 'required|string',
            'action' => 'required|string|in:read,write',
        ]);

        $modelClass = $validated['model_class'];
        $isWhitelisted = in_array($modelClass, GlobalResourceWhitelist::getWhitelistedModels(), true);

        if ($validated['action'] === 'write') {
            $canWrite = GlobalResourceWhitelist::canWrite();

            self::recordOperation(
                'check_write',
                $modelClass,
                null,
                ['action' => 'write', 'is_whitelisted' => $isWhitelisted],
                $canWrite,
                $canWrite ? null : '无写入权限'
            );

            return ApiResponse::success([
                'is_whitelisted' => $isWhitelisted,
                'can_write' => $canWrite,
            ]);
        }

        return ApiResponse::success([
            'is_whitelisted' => $isWhitelisted,
        ]);
    }
}

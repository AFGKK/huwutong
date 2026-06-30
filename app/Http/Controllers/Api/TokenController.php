<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TokenBlacklist;
use App\Services\TokenIntrospectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Token 内省与吊销管理 API
 *
 * M2-83 Token Introspection (RFC 7662)
 */
class TokenController extends Controller
{
    public function __construct(
        protected TokenIntrospectionService $introspectionService,
    ) {}

    /**
     * Token 内省端点
     *
     * POST /api/token/introspect
     * 兼容 OAuth2 Introspection (RFC 7662) 格式
     *
     * 可用于内部微服务验证 Token 有效性
     */
    public function introspect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string',
        ]);

        $result = $this->introspectionService->introspect($data['token']);

        return response()->json($result);
    }

    /**
     * 吊销当前 Token（增强版 logout）
     *
     * POST /api/token/revoke
     */
    public function revokeCurrent(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if ($token) {
            $this->introspectionService->revokeToken(
                (string) $token->getKey(),
                'user_revoked',
                $user->id,
            );
        }

        return response()->json([
            'success' => true,
            'message' => '当前 Token 已吊销',
        ]);
    }

    /**
     * 吊销指定 Token（管理员）
     *
     * POST /api/admin/tokens/{tokenId}/revoke
     */
    public function adminRevokeToken(int $tokenId, Request $request): JsonResponse
    {
        $token = PersonalAccessToken::findOrFail($tokenId);

        $this->introspectionService->revokeToken(
            (string) $token->getKey(),
            'admin_revoked',
            $token->tokenable_id,
        );

        return response()->json([
            'success' => true,
            'message' => "Token #{$tokenId} 已被管理员吊销",
        ]);
    }

    /**
     * 吊销用户所有 Token（管理员）
     *
     * POST /api/admin/users/{userId}/tokens/revoke-all
     */
    public function adminRevokeUserTokens(int $userId, Request $request): JsonResponse
    {
        $count = $this->introspectionService->revokeAllUserTokens(
            $userId,
            'admin_revoke_all',
        );

        return response()->json([
            'success' => true,
            'message' => "已吊销用户 {$userId} 的 {$count} 个 Token",
            'data' => ['revoked_count' => $count],
        ]);
    }

    /**
     * 获取当前用户的会话列表
     *
     * GET /api/tokens
     */
    public function myTokens(Request $request): JsonResponse
    {
        $tokens = PersonalAccessToken::where('tokenable_id', $request->user()->id)
            ->where('tokenable_type', get_class($request->user()))
            ->get()
            ->map(function ($token) use ($request) {
                $isCurrent = (string) $token->getKey() === (string) $request->user()->currentAccessToken()?->getKey();
                $blacklisted = $this->introspectionService->isBlacklisted((string) $token->getKey());

                return [
                    'id' => $token->getKey(),
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'is_current' => $isCurrent,
                    'is_revoked' => $blacklisted,
                    'last_used_at' => $token->last_used_at,
                    'expires_at' => $token->expires_at,
                    'ip_address' => $token->ip_address,
                    'user_agent' => $token->user_agent,
                    'created_at' => $token->created_at,
                ];
            });

        return response()->json(['success' => true, 'data' => $tokens]);
    }

    /**
     * 获取所有 Token 黑名单概览（管理员）
     *
     * GET /api/admin/revoked-tokens
     */
    public function adminRevokedTokens(Request $request): JsonResponse
    {
        $query = TokenBlacklist::with(['tokenable' => function ($q) {
            $q->select('id', 'name', 'email');
        }])
        ->orderBy('revoked_at', 'desc');

        if ($request->filled('reason')) {
            $query->where('reason', $request->input('reason'));
        }

        $logs = $query->paginate($request->input('per_page', 30));

        return response()->json(['success' => true, 'data' => $logs]);
    }
}

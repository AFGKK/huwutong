<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CiCdToken;
use App\Models\CiCdUsageLog;
use App\Services\CiCdLicenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * CI/CD 自动授权控制器
 * 
 * 提供面向 CI/CD 流水线的 License 获取/激活端点
 * 支持: GitHub Actions / GitLab CI / Jenkins / 通用 curl
 */
class CiCdController extends Controller
{
    public function __construct(
        protected CiCdLicenseService $ciService,
    ) {}

    // ═══════════════════════════════════════════════════════════
    // CI/CD 端点（通过 Bearer Token 认证，适合 curl 调用）
    // ═══════════════════════════════════════════════════════════

    /**
     * 获取 License 列表（CI/CD 用）
     * 
     * GET /api/ci/license/fetch
     * Header: Authorization: Bearer <ci_token>
     */
    public function fetchLicense(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['success' => false, 'error' => 'Missing Authorization header'], 401);
        }

        $context = CiCdLicenseService::getCiContext();
        $result = $this->ciService->fetchLicense($token, $context);

        if (!$result['success']) {
            return response()->json($result, 403);
        }

        return response()->json($result);
    }

    /**
     * 激活 License（CI/CD 用）
     * 
     * POST /api/ci/license/activate
     * Header: Authorization: Bearer <ci_token>
     * Body: {"license_key": "xxxx-xxxx-xxxx"}
     */
    public function activateLicense(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['success' => false, 'error' => 'Missing Authorization header'], 401);
        }

        $validated = $request->validate([
            'license_key' => 'required|string',
        ]);

        $context = array_merge(
            CiCdLicenseService::getCiContext(),
            ['license_key' => $validated['license_key']]
        );

        $result = $this->ciService->activateLicense($token, $validated['license_key'], $context);

        if (!$result['success']) {
            return response()->json($result, 403);
        }

        return response()->json($result);
    }

    /**
     * 令牌信息（CI/CD 用）
     * 
     * GET /api/ci/token/info
     * Header: Authorization: Bearer <ci_token>
     */
    public function tokenInfo(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['success' => false, 'error' => 'Missing Authorization header'], 401);
        }

        return response()->json($this->ciService->tokenInfo($token));
    }

    // ═══════════════════════════════════════════════════════════
    // 管理后台端点
    // ═══════════════════════════════════════════════════════════

    /**
     * 令牌列表
     */
    public function tokens(Request $request)
    {
        $query = CiCdToken::with('user:id,name');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('created_at')->paginate(20),
        ]);
    }

    /**
     * 创建令牌
     */
    public function storeToken(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'scopes' => 'required|array',
            'scopes.*' => 'in:license_read,license_write,license_activate,all',
            'allowed_license_ids' => 'nullable|array',
            'allowed_license_ids.*' => 'integer|exists:licenses,id',
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
        ]);

        $token = CiCdToken::create([
            'tenant_id' => $request->user()->tenant_id ?? 1,
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'token' => CiCdToken::generateToken(),
            'description' => $validated['description'] ?? '',
            'scopes' => $validated['scopes'],
            'allowed_license_ids' => $validated['allowed_license_ids'] ?? null,
            'max_uses' => $validated['max_uses'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'data' => $token,
            'message' => __('app.controller_compat.ci_cd_ci_cd'),
        ], 201);
    }

    /**
     * 更新令牌
     */
    public function updateToken(Request $request, CiCdToken $ciCdToken)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'scopes' => 'sometimes|array',
            'scopes.*' => 'in:license_read,license_write,license_activate,all',
            'allowed_license_ids' => 'nullable|array',
            'allowed_license_ids.*' => 'integer|exists:licenses,id',
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
            'status' => 'sometimes|in:active,revoked',
            'revoked_reason' => 'nullable|string|max:500',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'revoked') {
            $validated['revoked_at'] = now();
        }

        $ciCdToken->update($validated);

        return response()->json(['success' => true, 'data' => $ciCdToken]);
    }

    /**
     * 删除令牌
     */
    public function destroyToken(CiCdToken $ciCdToken)
    {
        $ciCdToken->usageLogs()->delete();
        $ciCdToken->delete();

        return response()->json(['success' => true]);
    }

    /**
     * 使用日志
     */
    public function usageLogs(CiCdToken $ciCdToken)
    {
        $logs = $ciCdToken->usageLogs()->orderByDesc('created_at')->paginate(20);

        return response()->json(['success' => true, 'data' => $logs]);
    }

    /**
     * CI/CD 集成示例代码
     */
    public function examples()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'github_actions' => CiCdLicenseService::getGitHubActionsExample(),
                'gitlab_ci' => CiCdLicenseService::getGitLabCiExample(),
                'jenkins' => CiCdLicenseService::getJenkinsExample(),
                'curl' => CiCdLicenseService::getCurlExample(),
                'api_endpoints' => [
                    'fetch' => [
                        'method' => 'GET',
                        'url' => '/api/ci/license/fetch',
                        'auth' => 'Authorization: Bearer <ci_token>',
                    ],
                    'activate' => [
                        'method' => 'POST',
                        'url' => '/api/ci/license/activate',
                        'auth' => 'Authorization: Bearer <ci_token>',
                        'body' => '{"license_key": "xxxx-xxxx"}',
                    ],
                    'token_info' => [
                        'method' => 'GET',
                        'url' => '/api/ci/token/info',
                        'auth' => 'Authorization: Bearer <ci_token>',
                    ],
                ],
            ],
        ]);
    }

    /**
     * 统计
     */
    public function stats()
    {
        $totalTokens = CiCdToken::count();
        $activeTokens = CiCdToken::where('status', 'active')->count();
        $totalCalls = CiCdUsageLog::count();
        $todayCalls = CiCdUsageLog::whereDate('created_at', today())->count();

        $byProvider = CiCdUsageLog::selectRaw('ci_provider, count(*) as count')
            ->groupBy('ci_provider')
            ->pluck('count', 'ci_provider');

        return response()->json([
            'success' => true,
            'data' => [
                'total_tokens' => $totalTokens,
                'active_tokens' => $activeTokens,
                'total_calls' => $totalCalls,
                'today_calls' => $todayCalls,
                'by_provider' => $byProvider,
            ],
        ]);
    }
}

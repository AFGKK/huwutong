<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\DemoService;
use Illuminate\Http\Request;

class DemoController extends Controller
{
    public function __construct(
        protected DemoService $demoService,
    ) {}

    /**
     * POST /api/demo/start
     * 创建新演示会话
     */
    public function start(Request $request)
    {
        $sessionId = $request->cookie('demo_session') ?? $request->input('session_id', uniqid('demo-', true));
        $session = $this->demoService->createSession(
            $sessionId,
            $request->ip(),
            $request->userAgent()
        );

        return ApiResponse::success([
            'token' => $session->token,
            'session_id' => $sessionId,
            'expires_at' => $session->expires_at,
            'remaining_seconds' => $session->remaining_seconds,
            'step' => $this->demoService->getCurrentStep($session),
        ]);
    }

    /**
     * GET /api/demo/data?type={type}
     * 获取演示数据
     */
    public function data(Request $request)
    {
        $session = $this->resolveSession($request);
        if (!$session) {
            return ApiResponse::success(['message' => '会话已过期或无效'], 401);
        }

        $type = $request->input('type', 'all');
        $data = $this->demoService->getDemoData($session, $type);

        return ApiResponse::success($data);
    }

    /**
     * POST /api/demo/step
     * 推进引导步骤
     */
    public function step(Request $request)
    {
        $session = $this->resolveSession($request);
        if (!$session) {
            return ApiResponse::success(['message' => '会话已过期或无效'], 401);
        }

        $step = (int) $request->input('step', 0);
        $session = $this->demoService->advanceStep($session, $step);

        return ApiResponse::success($this->demoService->getCurrentStep($session));
    }

    /**
     * POST /api/demo/action
     * 记录完成的操作
     */
    public function action(Request $request)
    {
        $session = $this->resolveSession($request);
        if (!$session) {
            return ApiResponse::success(['message' => '会话已过期或无效'], 401);
        }

        $action = $request->input('action', '');
        if (!$action) {
            return ApiResponse::success(['message' => 'action 必填'], 422);
        }

        $session = $this->demoService->completeAction($session, $action);
        return ApiResponse::success(['completed_actions' => $session->completed_actions]);
    }

    /**
     * POST /api/demo/heartbeat
     * 心跳更新
     */
    public function heartbeat(Request $request)
    {
        $session = $this->resolveSession($request);
        if (!$session) {
            return ApiResponse::success(['message' => '会话已过期', 'expired' => true], 401);
        }

        return ApiResponse::success($this->demoService->heartbeat($session));
    }

    /**
     * POST /api/demo/extend
     * 延长会话
     */
    public function extend(Request $request)
    {
        $session = $this->resolveSession($request);
        if (!$session) {
            return ApiResponse::success(['message' => '会话已过期或无效'], 401);
        }

        $minutes = (int) $request->input('minutes', 15);
        $session = $this->demoService->extendSession($session, $minutes);

        return ApiResponse::success([
            'expires_at' => $session->expires_at,
            'remaining_seconds' => $session->remaining_seconds,
        ]);
    }

    /**
     * POST /api/demo/complete
     * 完成演示（CTA注册后）
     */
    public function complete(Request $request)
    {
        $session = $this->resolveSession($request);
        if (!$session) {
            return ApiResponse::success(['message' => '会话已过期或无效'], 401);
        }

        $this->demoService->completeSession($session);
        return ApiResponse::success(['message' => '演示已完成，感谢体验']);
    }

    /**
     * 解析当前会话令牌
     */
    protected function resolveSession(Request $request): ?\App\Models\DemoSession
    {
        $token = $request->bearerToken()
            ?? $request->input('token')
            ?? $request->header('X-Demo-Token');

        if (!$token) return null;

        return $this->demoService->getSession($token);
    }

    /**
     * POST /api/demo/register
     * CTA 引导注册（演示完成后注册真实用户）
     */
    public function register(Request $request)
    {
        $session = $this->resolveSession($request);
        if (!$session) {
            return ApiResponse::success(['message' => '会话已过期或无效，请重新开始演示'], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'company' => 'nullable|string|max:200',
            'password' => 'nullable|string|min:6|max:100',
        ]);

        try {
            $result = $this->demoService->registerFromDemo($session, $validated);
            return ApiResponse::success($result);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::success(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return ApiResponse::success(['message' => '注册失败: ' . $e->getMessage()], 500);
        }
    }

    // ─── 管理端 API (需要认证) ───

    /**
     * GET /api/admin/demo/analytics
     * 演示分析数据
     */
    public function analytics()
    {
        $this->authorize('viewAny', \App\Models\DemoSession::class);

        return ApiResponse::success($this->demoService->getAnalytics());
    }

    /**
     * GET /api/admin/demo/config
     * 获取演示配置
     */
    public function getConfig()
    {
        $this->authorize('viewAny', \App\Models\DemoSession::class);

        return ApiResponse::success($this->demoService->getConfig());
    }

    /**
     * PUT /api/admin/demo/config
     * 更新演示配置
     */
    public function updateConfig(Request $request)
    {
        $this->authorize('update', \App\Models\DemoSession::class);

        $validated = $request->validate([
            'session_duration_minutes' => 'nullable|integer|min:5|max:120',
            'extend_minutes' => 'nullable|integer|min:5|max:60',
            'enabled' => 'nullable|boolean',
            'cta_title' => 'nullable|string|max:100',
            'cta_description' => 'nullable|string|max:500',
        ]);

        return ApiResponse::success($this->demoService->updateConfig($validated));
    }

    /**
     * GET /api/admin/demo/embed-code
     * 获取嵌入代码
     */
    public function embedCode()
    {
        return ApiResponse::success([
            'embed_code' => $this->demoService->getEmbedCode(),
            'embed_js_url' => url('/demo/embed.js'),
        ]);
    }

    /**
     * GET /api/admin/demo/sessions
     * 会话列表
     */
    public function sessions(Request $request)
    {
        $this->authorize('viewAny', \App\Models\DemoSession::class);

        $query = \App\Models\DemoSession::query();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $sessions = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return ApiResponse::success($sessions);
    }
}

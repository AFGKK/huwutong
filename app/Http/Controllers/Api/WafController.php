<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\WafIpList;
use App\Models\WafRule;
use App\Services\WafService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WafController extends Controller
{
    public function __construct(
        protected WafService $wafService
    ) {}

    // ─── 仪表盘 ────────────────────────────────────

    /**
     * WAF 仪表盘
     * GET /api/v1/admin/waf/dashboard
     */
    public function dashboard(): JsonResponse
    {
        $data = $this->wafService->getDashboard();

        return ApiResponse::success($data, 'WAF 仪表盘获取成功');
    }

    // ─── 规则管理 ──────────────────────────────────

    /**
     * 规则列表
     * GET /api/v1/admin/waf/rules
     */
    public function rules(Request $request): JsonResponse
    {
        $filters = $request->only(['category', 'severity', 'is_active']);
        $data = $this->wafService->getRules($filters);

        return ApiResponse::success($data, '规则列表获取成功');
    }

    /**
     * 创建规则
     * POST /api/v1/admin/waf/rules
     */
    public function storeRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'category' => 'required|string|in:sqli,xss,path_traversal,cmd_injection,file_inclusion,ssrf,custom',
            'severity' => 'required|string|in:low,medium,high,critical',
            'mode' => 'required|string|in:block,detect,simulate',
            'match_type' => 'required|string|in:regex,exact,prefix,suffix,contains',
            'pattern' => 'required|string',
            'target' => 'string|in:all,query,body,headers,cookies,uri',
            'action' => 'string|in:block,challenge,log,allow',
            'description' => 'nullable|string',
            'recommendation' => 'nullable|string',
            'scope' => 'nullable|array',
            'priority' => 'integer|min:1|max:9999',
        ]);

        $rule = $this->wafService->createRule($validated);

        return ApiResponse::success($rule, '规则创建成功');
    }

    /**
     * 更新规则
     * PUT /api/v1/admin/waf/rules/{id}
     */
    public function updateRule(Request $request, WafRule $wafRule): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:200',
            'category' => 'string|in:sqli,xss,path_traversal,cmd_injection,file_inclusion,ssrf,custom',
            'severity' => 'string|in:low,medium,high,critical',
            'mode' => 'string|in:block,detect,simulate',
            'match_type' => 'string|in:regex,exact,prefix,suffix,contains',
            'pattern' => 'string',
            'target' => 'string|in:all,query,body,headers,cookies,uri',
            'action' => 'string|in:block,challenge,log,allow',
            'description' => 'nullable|string',
            'recommendation' => 'nullable|string',
            'scope' => 'nullable|array',
            'priority' => 'integer|min:1|max:9999',
            'is_active' => 'boolean',
        ]);

        $rule = $this->wafService->updateRule($wafRule, $validated);

        return ApiResponse::success($rule, '规则更新成功');
    }

    /**
     * 删除规则
     * DELETE /api/v1/admin/waf/rules/{id}
     */
    public function destroyRule(WafRule $wafRule): JsonResponse
    {
        $this->wafService->deleteRule($wafRule);

        return ApiResponse::success(null, '规则已删除');
    }

    /**
     * 切换规则状态
     * POST /api/v1/admin/waf/rules/{id}/toggle
     */
    public function toggleRule(WafRule $wafRule): JsonResponse
    {
        $rule = $this->wafService->toggleRule($wafRule);

        return ApiResponse::success($rule, $rule->is_active ? '规则已启用' : '规则已禁用');
    }

    /**
     * 导入默认规则
     * POST /api/v1/admin/waf/rules/seed
     */
    public function seedRules(): JsonResponse
    {
        $result = $this->wafService->seedDefaultRules();

        return ApiResponse::success($result, $result['message']);
    }

    // ─── IP 黑白名单 ──────────────────────────────

    /**
     * IP 列表
     * GET /api/v1/admin/waf/ip-list
     */
    public function ipList(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $data = $this->wafService->getIpList($type);

        return ApiResponse::success($data, 'IP 列表获取成功');
    }

    /**
     * 添加 IP
     * POST /api/v1/admin/waf/ip-list
     */
    public function addIp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip' => 'required|string|max:45',
            'type' => 'required|string|in:blacklist,whitelist,challenge',
            'reason' => 'nullable|string|max:500',
            'expires_at' => 'nullable|date',
        ]);

        $ip = $this->wafService->addIp($validated);

        return ApiResponse::success($ip, 'IP 已添加');
    }

    /**
     * 批量添加 IP
     * POST /api/v1/admin/waf/ip-list/batch
     */
    public function batchAddIp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ips' => 'required|array',
            'ips.*.ip' => 'required|string|max:45',
            'type' => 'required|string|in:blacklist,whitelist,challenge',
            'reason' => 'nullable|string|max:500',
        ]);

        $result = $this->wafService->batchAddIp($validated['ips'], $validated['type'], $validated['reason'] ?? null);

        return ApiResponse::success($result, $result['message']);
    }

    /**
     * 删除 IP
     * DELETE /api/v1/admin/waf/ip-list/{id}
     */
    public function deleteIp(WafIpList $wafIpList): JsonResponse
    {
        $this->wafService->deleteIp($wafIpList);

        return ApiResponse::success(null, 'IP 已删除');
    }

    /**
     * IP 检查
     * GET /api/v1/admin/waf/ip-list/check?ip=xxx
     */
    public function checkIp(Request $request): JsonResponse
    {
        $request->validate(['ip' => 'required|string|max:45']);
        $data = $this->wafService->checkIp($request->input('ip'));

        return ApiResponse::success($data, 'IP 检查完成');
    }

    // ─── 攻击日志 ──────────────────────────────────

    /**
     * 攻击日志
     * GET /api/v1/admin/waf/logs
     */
    public function logs(Request $request): JsonResponse
    {
        $filters = $request->only(['ip', 'category', 'severity', 'date_from', 'date_to', 'page', 'per_page']);
        $data = $this->wafService->getAttackLogs($filters);

        return ApiResponse::success($data, '攻击日志获取成功');
    }

    /**
     * 攻击趋势
     * GET /api/v1/admin/waf/trend
     */
    public function trend(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 7);
        $data = $this->wafService->getTrend($days);

        return ApiResponse::success($data, '攻击趋势获取成功');
    }

    // ─── 配置管理 ──────────────────────────────────

    /**
     * 获取 WAF 配置
     * GET /api/v1/admin/waf/config
     */
    public function getConfig(): JsonResponse
    {
        return ApiResponse::success($this->wafService->getConfig(), 'WAF 配置获取成功');
    }

    /**
     * 更新 WAF 配置
     * PUT /api/v1/admin/waf/config
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $data = $request->only(['enabled', 'mode', 'cc_enabled', 'cc_mode', 'rules_enabled', 'rules_mode']);
        $result = $this->wafService->updateConfig($data);

        return ApiResponse::success($result, $result['message']);
    }

    /**
     * 清除日志（过期）
     * POST /api/v1/admin/waf/logs/prune
     */
    public function pruneLogs(): JsonResponse
    {
        $result = $this->wafService->pruneLogs();

        return ApiResponse::success($result, $result['message']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\EmailDripCampaign;
use App\Services\EmailDripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 邮件营销 Drip 序列控制器 (M2-102)
 */
class EmailDripController extends Controller
{
    public function __construct(
        protected EmailDripService $drip,
    ) {}

    /**
     * 仪表盘
     * GET /api/admin/email-drip/dashboard
     */
    public function dashboard(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        return ApiResponse::success($this->drip->getDashboard($tenantId));
    }

    /**
     * 活动列表
     * GET /api/admin/email-drip/campaigns
     */
    public function campaigns(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $filters = $request->only(['status']);
        return ApiResponse::success($this->drip->listCampaigns($tenantId, $filters));
    }

    /**
     * 创建活动
     * POST /api/admin/email-drip/campaigns
     */
    public function storeCampaign(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'trigger_event' => 'required|string|max:100',
            'description' => 'nullable|string|max:2000',
            'target_filters' => 'nullable|array',
        ]);

        $campaign = $this->drip->createCampaign($tenantId, $validated);
        return ApiResponse::created($campaign, '营销活动已创建');
    }

    /**
     * 活动详情
     * GET /api/admin/email-drip/campaigns/{campaign}
     */
    public function showCampaign(EmailDripCampaign $campaign): JsonResponse
    {
        return ApiResponse::success($this->drip->getCampaign($campaign->id));
    }

    /**
     * 添加序列步骤
     * POST /api/admin/email-drip/campaigns/{campaign}/sequences
     */
    public function storeSequence(Request $request, EmailDripCampaign $campaign): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'delay_days' => 'required|integer|min:0|max:90',
            'subject' => 'required|string|max:500',
            'content' => 'required|string',
            'template_id' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $seq = $this->drip->addSequence($campaign->id, $validated);
        return ApiResponse::created($seq, '序列步骤已添加');
    }

    /**
     * 启动活动
     * POST /api/admin/email-drip/campaigns/{campaign}/activate
     */
    public function activate(EmailDripCampaign $campaign): JsonResponse
    {
        $campaign = $this->drip->activateCampaign($campaign->id);
        return ApiResponse::success($campaign, '营销活动已启动');
    }

    /**
     * 暂停活动
     * POST /api/admin/email-drip/campaigns/{campaign}/pause
     */
    public function pause(EmailDripCampaign $campaign): JsonResponse
    {
        $campaign = $this->drip->pauseCampaign($campaign->id);
        return ApiResponse::success($campaign, '营销活动已暂停');
    }

    /**
     * 追踪打开（公开）
     * GET /api/email-drip/track-open/{recipientId}
     */
    public function trackOpen(int $recipientId): JsonResponse
    {
        $this->drip->trackOpen($recipientId);
        return response()->json(null, 204);
    }

    /**
     * 追踪点击（公开）
     * GET /api/email-drip/track-click/{recipientId}
     */
    public function trackClick(int $recipientId): JsonResponse
    {
        $this->drip->trackClick($recipientId);
        return response()->json(null, 204);
    }

    /**
     * 获取触发器列表
     * GET /api/admin/email-drip/triggers
     */
    public function triggers(): JsonResponse
    {
        return ApiResponse::success(config('email-drip.sequences.triggers', []));
    }
}

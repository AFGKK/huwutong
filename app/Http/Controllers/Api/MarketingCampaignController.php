<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\MarketingCampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MarketingCampaignController extends Controller
{
    public function __construct(
        protected MarketingCampaignService $service
    ) {}

    /**
     * 营销仪表盘
     */
    public function dashboard(Request $request)
    {
        return ApiResponse::success(
            $this->service->getDashboard($request->user()->tenant_id)
        );
    }

    /**
     * 活动列表
     */
    public function index(Request $request)
    {
        return ApiResponse::success(
            $this->service->listCampaigns(
                $request->user()->tenant_id,
                $request->only(['status', 'type', 'search', 'per_page'])
            )
        );
    }

    /**
     * 创建活动
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'type' => 'required|string|in:email,sms,in_app,multi_channel',
            'audience_type' => 'nullable|string|in:all,segment,custom',
            'segment_id' => 'nullable|integer|exists:customer_segments,id',
            'audience_filter' => 'nullable|array',
            'scheduled_at' => 'nullable|date',
            'timezone' => 'nullable|string|max:50',
            'budget' => 'nullable|numeric|min:0',
            'channel_config' => 'nullable|array',
            'steps' => 'nullable|array',
            'steps.*.action_type' => 'required_with:steps|string|in:send_email,send_sms,send_notification,wait,condition,segment',
            'steps.*.delay_type' => 'nullable|string|in:immediate,delay,schedule',
            'steps.*.delay_minutes' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success(
            $this->service->createCampaign(
                $request->user()->tenant_id,
                $request->user()->id,
                $request->all()
            )
        );
    }

    /**
     * 活动详情
     */
    public function show(Request $request, int $campaignId)
    {
        $campaign = \App\Models\MarketingCampaign::with(['steps', 'creator:id,name', 'segment:id,name'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($campaignId);

        return ApiResponse::success($campaign);
    }

    /**
     * 更新活动
     */
    public function update(Request $request, int $campaignId)
    {
        return ApiResponse::success(
            $this->service->updateCampaign(
                $request->user()->tenant_id,
                $campaignId,
                $request->only(['name', 'description', 'scheduled_at', 'timezone', 'budget', 'channel_config'])
            )
        );
    }

    /**
     * 删除活动
     */
    public function destroy(Request $request, int $campaignId)
    {
        $this->service->deleteCampaign($request->user()->tenant_id, $campaignId);
        return ApiResponse::success(['deleted' => true]);
    }

    /**
     * 启动活动
     */
    public function launch(Request $request, int $campaignId)
    {
        return ApiResponse::success(
            $this->service->launchCampaign($request->user()->tenant_id, $campaignId)
        );
    }

    /**
     * 暂停/继续
     */
    public function toggle(Request $request, int $campaignId)
    {
        return ApiResponse::success(
            $this->service->toggleCampaign($request->user()->tenant_id, $campaignId)
        );
    }

    /**
     * 完成活动
     */
    public function complete(Request $request, int $campaignId)
    {
        return ApiResponse::success(
            $this->service->completeCampaign($request->user()->tenant_id, $campaignId)
        );
    }

    /**
     * 取消活动
     */
    public function cancel(Request $request, int $campaignId)
    {
        return ApiResponse::success(
            $this->service->cancelCampaign($request->user()->tenant_id, $campaignId)
        );
    }

    /**
     * 更新步骤
     */
    public function updateSteps(Request $request, int $campaignId)
    {
        $validator = Validator::make($request->all(), [
            'steps' => 'required|array',
            'steps.*.action_type' => 'required|string|in:send_email,send_sms,send_notification,wait,condition,segment',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success(
            $this->service->updateSteps(
                $request->user()->tenant_id,
                $campaignId,
                $request->steps
            )
        );
    }

    /**
     * 预览受众
     */
    public function previewAudience(Request $request)
    {
        $campaign = new \App\Models\MarketingCampaign();
        $campaign->audience_type = $request->audience_type ?? 'all';
        $campaign->segment_id = $request->segment_id;
        $campaign->audience_filter = $request->audience_filter;

        return ApiResponse::success([
            'count' => $this->service->countTargetAudience($request->user()->tenant_id, $campaign),
        ]);
    }

    /**
     * 模拟发送
     */
    public function simulateSend(Request $request, int $campaignId)
    {
        return ApiResponse::success(
            $this->service->simulateSend($request->user()->tenant_id, $campaignId)
        );
    }

    /**
     * D-24: 实际执行发送
     */
    public function send(Request $request, int $campaignId)
    {
        $request->validate([
            'batch_size' => 'integer|min:1|max:500',
        ]);

        return ApiResponse::success(
            $this->service->sendCampaign(
                $request->user()->tenant_id,
                $campaignId,
                ['batch_size' => $request->input('batch_size', 100)]
            )
        );
    }

    /**
     * 活动分析
     */
    public function analytics(Request $request, int $campaignId)
    {
        return ApiResponse::success(
            $this->service->getCampaignAnalytics($request->user()->tenant_id, $campaignId)
        );
    }

    /**
     * 活动列表（按状态汇总）
     */
    public function stats(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success([
            'status_breakdown' => \App\Models\MarketingCampaign::where('tenant_id', $tenantId)
                ->selectRaw("status, COUNT(*) as total")
                ->groupBy('status')
                ->get()
                ->pluck('total', 'status')
                ->toArray(),
        ]);
    }
}

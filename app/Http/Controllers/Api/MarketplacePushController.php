<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MarketplacePushCampaign;
use App\Services\MarketplacePushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplacePushController extends Controller
{
    protected MarketplacePushService $pushService;

    public function __construct(MarketplacePushService $pushService)
    {
        $this->pushService = $pushService;
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->pushService->getCampaigns($request->only(['status', 'type']), (int) $request->input('per_page', 20))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string|max:2000',
            'type' => 'required|in:marketing,update,promo,info',
            'target_type' => 'required|in:all,installed_app,category,specific_app',
            'target_app_id' => 'nullable|integer|exists:marketplace_apps,id',
            'target_category' => 'nullable|string|max:50',
            'link_type' => 'nullable|in:app,url',
            'link_value' => 'nullable|string|max:500',
            'scheduled_at' => 'nullable|date|after_or_equal:now',
        ]);

        $campaign = $this->pushService->createCampaign($validated, $request->user()->id);
        return ApiResponse::created($campaign, __("app.marketplace_push.msg_bf57d7b8"));
    }

    public function show(int $id): JsonResponse
    {
        $campaign = MarketplacePushCampaign::with('creator:id,name')->findOrFail($id);
        return ApiResponse::success($campaign);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'content' => 'sometimes|string|max:2000',
            'type' => 'sometimes|in:marketing,update,promo,info',
            'target_type' => 'sometimes|in:all,installed_app,category,specific_app',
            'target_app_id' => 'nullable|integer|exists:marketplace_apps,id',
            'target_category' => 'nullable|string|max:50',
            'link_type' => 'nullable|in:app,url',
            'link_value' => 'nullable|string|max:500',
            'scheduled_at' => 'nullable|date|after_or_equal:now',
        ]);

        try {
            $campaign = $this->pushService->updateCampaign($id, $validated);
            return ApiResponse::success($campaign, __("app.marketplace_push.msg_7c88f4d1"));
        } catch (\RuntimeException $e) {
            return ApiResponse::validationError($e->getMessage());
        }
    }

    public function send(int $id): JsonResponse
    {
        try {
            $campaign = $this->pushService->sendCampaign($id);
            $msg = $campaign->status === 'scheduled' ? '已定时，将在指定时间发送' : '推送已发送';
            return ApiResponse::success($campaign, $msg);
        } catch (\RuntimeException $e) {
            return ApiResponse::validationError($e->getMessage());
        }
    }

    public function cancel(int $id): JsonResponse
    {
        try {
            $campaign = $this->pushService->cancelCampaign($id);
            return ApiResponse::success($campaign, __("app.marketplace_push.msg_713ea743"));
        } catch (\RuntimeException $e) {
            return ApiResponse::validationError($e->getMessage());
        }
    }

    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->pushService->getStats());
    }

    public function destroy(int $id): JsonResponse
    {
        MarketplacePushCampaign::findOrFail($id)->delete();
        return ApiResponse::success(null, __("app.marketplace_push.msg_be93d1f5"));
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\FeatureAdoptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeatureAdoptionController extends Controller
{
    public function __construct(protected FeatureAdoptionService $adoptionService) {}

    /**
     * 仪表盘
     */
    public function dashboard(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.feature_adoption.validation_failed'), $validator->errors()->toArray());
        }

        $data = $this->adoptionService->getDashboard(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return ApiResponse::success($data);
    }

    /**
     * 功能详情
     */
    public function featureDetail(Request $request, string $featureKey): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.feature_adoption.validation_failed'), $validator->errors()->toArray());
        }

        $data = $this->adoptionService->getFeatureDetail(
            $featureKey,
            $request->input('start_date'),
            $request->input('end_date')
        );

        return ApiResponse::success($data);
    }

    /**
     * 分类详情
     */
    public function categoryDetail(Request $request, string $category): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.feature_adoption.validation_failed'), $validator->errors()->toArray());
        }

        $data = $this->adoptionService->getCategoryDetail(
            $category,
            $request->input('start_date'),
            $request->input('end_date')
        );

        return ApiResponse::success($data);
    }

    /**
     * 漏斗分析
     */
    public function funnel(Request $request, string $funnelKey): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.feature_adoption.validation_failed'), $validator->errors()->toArray());
        }

        $data = $this->adoptionService->getFunnel(
            $funnelKey,
            $request->input('start_date'),
            $request->input('end_date')
        );

        return ApiResponse::success($data);
    }

    /**
     * 趋势数据
     */
    public function trend(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.feature_adoption.validation_failed'), $validator->errors()->toArray());
        }

        $data = $this->adoptionService->getTrend(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return ApiResponse::success($data);
    }

    /**
     * 事件列表
     */
    public function events(Request $request): JsonResponse
    {
        $data = $this->adoptionService->getEvents($request->all());
        return ApiResponse::success($data);
    }

    /**
     * 记录事件（公开，前端埋点调用）
     */
    public function track(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'feature_key' => 'required|string|max:100',
            'action' => 'nullable|string|max:50',
            'session_id' => 'nullable|string|max:100',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.feature_adoption.validation_failed'), $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $data['user_id'] = $request->user()?->id;

        $event = $this->adoptionService->track($data['feature_key'], $data);

        return ApiResponse::success($event ? ['id' => $event->id] : null, __('app.api.feature_adoption.recorded'));
    }

    /**
     * 批量记录事件（公开）
     */
    public function batchTrack(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'events' => 'required|array|max:100',
            'events.*.feature_key' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.feature_adoption.validation_failed'), $validator->errors()->toArray());
        }

        $events = $request->input('events');
        foreach ($events as &$event) {
            $event['user_id'] = $request->user()?->id;
        }

        $count = $this->adoptionService->batchTrack($events);

        return ApiResponse::success(['count' => $count], __('app.api.feature_adoption.events_recorded', ['count' => $count]));
    }

    /**
     * 生成每日快照
     */
    public function generateSnapshot(): JsonResponse
    {
        $result = $this->adoptionService->generateDailySnapshot();
        return ApiResponse::success($result, __('app.api.feature_adoption.snapshot_generated'));
    }

    /**
     * 功能定义列表
     */
    public function featureDefs(): JsonResponse
    {
        return ApiResponse::success([
            'features' => config('feature-adoption.features', []),
            'categories' => config('feature-adoption.categories', []),
            'funnels' => config('feature-adoption.funnels', []),
        ]);
    }

    /**
     * 清理过期数据
     */
    public function prune(Request $request): JsonResponse
    {
        $days = (int) $request->input('retention_days', 365);
        $deleted = $this->adoptionService->prune($days);
        return ApiResponse::success(['deleted' => $deleted], __('app.api.feature_adoption.events_cleaned', ['deleted' => $deleted]));
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PersonalizedRecommendation;
use App\Models\UserBehavior;
use App\Services\PersonalizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PersonalizationController extends Controller
{
    public function __construct(
        protected PersonalizationService $service
    ) {}

    // ─── 用户行为追踪 ───

    public function recordBehavior(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_type' => 'required|string|max:80',
            'event_action' => 'nullable|string|max:200',
            'resource_type' => 'nullable|string|max:80',
            'resource_id' => 'nullable|integer',
            'session_id' => 'nullable|string|max:100',
            'page_url' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;
        $data['user_id'] = $request->user()->id;
        $data['customer_id'] = $request->user()->customer_id;

        return ApiResponse::success($this->service->recordBehavior($data), 201);
    }

    public function behaviorStats(Request $request)
    {
        return ApiResponse::success(
            $this->service->getBehaviorStats(
                $request->user()->tenant_id,
                $request->only(['event_type', 'customer_id', 'from', 'to'])
            )
        );
    }

    // ─── 用户偏好 ───

    public function getPreference(Request $request, string $key)
    {
        return ApiResponse::success([
            'key' => $key,
            'value' => $this->service->getPreference($request->user()->id, $key),
        ]);
    }

    public function setPreference(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|max:100',
            'value' => 'nullable',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $pref = $this->service->setPreference(
            $request->user()->tenant_id,
            $request->user()->id,
            $request->input('key'),
            $request->input('value'),
            $request->user()->customer_id,
        );

        return ApiResponse::success($pref);
    }

    public function getAllPreferences(Request $request)
    {
        return ApiResponse::success($this->service->getAllPreferences($request->user()->id));
    }

    // ─── 推荐引擎 ───

    public function generateRecommendations(Request $request)
    {
        $customerId = $request->input('customer_id', $request->user()->customer_id);
        if (!$customerId) {
            return ApiResponse::error('REQUEST_ERROR', '缺少客户ID', 400);
        }

        $recs = $this->service->generateRecommendations($request->user()->tenant_id, $customerId);
        return ApiResponse::success($recs);
    }

    public function getRecommendations(Request $request)
    {
        $customerId = $request->input('customer_id', $request->user()->customer_id);
        if (!$customerId) {
            return ApiResponse::error('REQUEST_ERROR', '缺少客户ID', 400);
        }

        return ApiResponse::success(
            $this->service->getActiveRecommendations($request->user()->tenant_id, $customerId)
        );
    }

    public function dismissRecommendation(PersonalizedRecommendation $personalizedRecommendation)
    {
        $this->service->dismissRecommendation($personalizedRecommendation->id);
        return ApiResponse::success(['dismissed' => true]);
    }

    public function clickRecommendation(PersonalizedRecommendation $personalizedRecommendation)
    {
        $this->service->clickRecommendation($personalizedRecommendation->id);
        return ApiResponse::success(['clicked' => true]);
    }

    public function refreshAllRecommendations(Request $request)
    {
        return ApiResponse::success(
            $this->service->refreshAllRecommendations($request->user()->tenant_id)
        );
    }

    // ─── 个性化门户主页 ───

    public function personalizedHomepage(Request $request)
    {
        $customerId = $request->user()->customer_id;
        if (!$customerId) {
            return ApiResponse::error('REQUEST_ERROR', '缺少客户信息', 400);
        }

        return ApiResponse::success(
            $this->service->getPersonalizedHomepage(
                $request->user()->tenant_id,
                $customerId,
                $request->user()->id,
            )
        );
    }

    // ─── 管理端 ───

    public function adminDashboard(Request $request)
    {
        return ApiResponse::success(
            $this->service->getAdminDashboard($request->user()->tenant_id)
        );
    }

    // ─── 元数据 ───

    public function eventTypes()
    {
        return ApiResponse::success(UserBehavior::EVENT_TYPES);
    }
}

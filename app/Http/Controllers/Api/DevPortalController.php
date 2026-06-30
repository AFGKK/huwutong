<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DevPortalService;
use Illuminate\Http\JsonResponse;

/**
 * 开发者门户 (M2-86)
 */
class DevPortalController extends Controller
{
    public function __construct(
        protected DevPortalService $portal,
    ) {}

    /**
     * 门户看板（需认证）
     */
    public function dashboard(): JsonResponse
    {
        $data = $this->portal->dashboard(auth()->id());
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * 门户配置数据（公开）
     */
    public function publicData(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->portal->publicData()]);
    }

    /**
     * SDK 列表
     */
    public function sdks(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->portal->getSdkList()]);
    }

    /**
     * 快速开始步骤
     */
    public function quickstartSteps(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->portal->getQuickstartSteps()]);
    }
}

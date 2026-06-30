<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\GeoLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 设备地理位置记录与地域分布可视化 (M2-26)
 */
class GeoLocationController extends Controller
{
    public function __construct(protected GeoLocationService $geoService) {}

    /**
     * 仪表盘总览
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success($this->geoService->getDashboard($tenantId));
    }

    /**
     * 地域分布统计（柱状图数据）
     */
    public function stats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
        if ($validator->fails()) {
            return ApiResponse::validationError('参数验证失败', $validator->errors()->toArray());
        }

        $tenantId = $request->user()->tenant_id;
        $stats = $this->geoService->getRegionalStats(
            $tenantId,
            $request->input('start_date'),
            $request->input('end_date')
        );

        return ApiResponse::success($stats);
    }

    /**
     * 世界地图标记数据
     */
    public function mapData(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
        if ($validator->fails()) {
            return ApiResponse::validationError('参数验证失败', $validator->errors()->toArray());
        }

        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success(
            $this->geoService->getMapData(
                $tenantId,
                $request->input('start_date'),
                $request->input('end_date')
            )
        );
    }

    /**
     * 地理位置记录列表
     */
    public function records(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success(
            $this->geoService->getRecords($tenantId, $request->all())
        );
    }

    /**
     * 手动记录地理位置
     */
    public function record(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ip' => 'required|ip',
            'device_id' => 'nullable|integer|exists:devices,id',
            'license_id' => 'nullable|integer|exists:licenses,id',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'source' => 'nullable|string|in:activation,validation,heartbeat,manual',
        ]);
        if ($validator->fails()) {
            return ApiResponse::validationError('参数验证失败', $validator->errors()->toArray());
        }

        $tenantId = $request->user()->tenant_id;
        $record = $this->geoService->record(
            $tenantId,
            $request->input('ip'),
            $request->input('device_id'),
            $request->input('license_id'),
            $request->input('customer_id'),
            $request->input('source', 'manual')
        );

        return ApiResponse::created($record);
    }

    /**
     * 获取黑名单
     */
    public function blacklist(): JsonResponse
    {
        return ApiResponse::success([
            'countries' => $this->geoService->getBlacklist(),
        ]);
    }

    /**
     * 更新黑名单
     */
    public function updateBlacklist(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'countries' => 'required|array',
            'countries.*' => 'string|size:2',
        ]);
        if ($validator->fails()) {
            return ApiResponse::validationError('参数验证失败', $validator->errors()->toArray());
        }

        $this->geoService->updateBlacklist($request->input('countries'));

        return ApiResponse::success(['countries' => $this->geoService->getBlacklist()], '黑名单已更新');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\GeoFenceService;
use App\Services\IpRestrictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * License 访问限制控制器 (M2-92 IP范围限制 + M2-93 地理围栏)
 */
class LicenseRestrictionController extends Controller
{
    public function __construct(
        protected IpRestrictionService $ipRestriction,
        protected GeoFenceService $geoFence,
    ) {
    }

    // ─── IP 范围限制 ───

    /**
     * 获取 IP 范围限制配置
     */
    public function getIpRestriction(int $licenseId): JsonResponse
    {
        $config = $this->ipRestriction->getConfig($licenseId);
        return ApiResponse::success([
            'config' => $config,
            'settings' => config('license-restrictions.ip_restriction'),
        ]);
    }

    /**
     * 保存 IP 范围限制配置
     */
    public function saveIpRestriction(Request $request, int $licenseId): JsonResponse
    {
        $data = $request->validate([
            'is_active' => 'boolean',
            'action' => 'string|in:block,allow,audit',
            'ip_ranges' => 'nullable|array',
            'ip_ranges.*' => 'string|max:50',
            'ip_whitelist' => 'nullable|array',
            'ip_whitelist.*' => 'string|max:45',
            'ip_blacklist' => 'nullable|array',
            'ip_blacklist.*' => 'string|max:45',
            'description' => 'nullable|string|max:500',
        ]);

        $userId = $request->user()?->id;
        $restriction = $this->ipRestriction->saveConfig($licenseId, $data, $userId);

        return ApiResponse::success(['config' => $restriction->fresh()], 'IP 范围限制已保存');
    }

    /**
     * 删除 IP 范围限制
     */
    public function deleteIpRestriction(int $licenseId): JsonResponse
    {
        $this->ipRestriction->deleteConfig($licenseId);
        return ApiResponse::success(null, 'IP 范围限制已删除');
    }

    /**
     * 测试 IP 是否被允许
     */
    public function testIp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_id' => 'required|integer',
            'ip' => 'required|string|max:45',
        ]);

        $result = $this->ipRestriction->check($data['license_id'], $data['ip'], 'test');
        return ApiResponse::success($result);
    }

    // ─── 地理围栏 ───

    /**
     * 获取地理围栏配置
     */
    public function getGeoFence(int $licenseId): JsonResponse
    {
        $config = $this->geoFence->getConfig($licenseId);
        return ApiResponse::success([
            'config' => $config,
            'settings' => config('license-restrictions.geo_fence'),
            'countries' => $this->geoFence->getCountries(),
        ]);
    }

    /**
     * 保存地理围栏配置
     */
    public function saveGeoFence(Request $request, int $licenseId): JsonResponse
    {
        $data = $request->validate([
            'is_active' => 'boolean',
            'action' => 'string|in:block,allow,audit',
            'allowed_countries' => 'nullable|array',
            'allowed_countries.*' => 'string|size:2',
            'blocked_countries' => 'nullable|array',
            'blocked_countries.*' => 'string|size:2',
            'unknown_location_action' => 'string|in:allow,block,audit',
            'description' => 'nullable|string|max:500',
        ]);

        $userId = $request->user()?->id;
        $restriction = $this->geoFence->saveConfig($licenseId, $data, $userId);

        return ApiResponse::success(['config' => $restriction->fresh()], '地理围栏已保存');
    }

    /**
     * 删除地理围栏
     */
    public function deleteGeoFence(int $licenseId): JsonResponse
    {
        $this->geoFence->deleteConfig($licenseId);
        return ApiResponse::success(null, '地理围栏已删除');
    }

    /**
     * 测试地理位置
     */
    public function testGeo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_id' => 'required|integer',
            'ip' => 'required|string|max:45',
        ]);

        $result = $this->geoFence->check($data['license_id'], $data['ip'], 'test');
        return ApiResponse::success($result);
    }

    // ─── 公共方法 ───

    /**
     * 获取国家列表
     */
    public function countries(): JsonResponse
    {
        return ApiResponse::success([
            'countries' => $this->geoFence->getCountries(),
        ]);
    }

    /**
     * 获取限制日志
     */
    public function logs(Request $request): JsonResponse
    {
        $query = \App\Models\LicenseRestrictionLog::query()
            ->orderBy('created_at', 'desc');

        if ($request->filled('license_id')) {
            $query->where('restrictable_id', $request->input('license_id'))
                ->where('restrictable_type', 'license');
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('result')) {
            $query->where('result', $request->input('result'));
        }

        $logs = $query->paginate($request->input('per_page', 20));

        return ApiResponse::success($logs);
    }
}

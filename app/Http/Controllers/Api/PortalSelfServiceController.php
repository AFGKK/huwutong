<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\UpdatePackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 门户自助能力（优化方案 2.2）
 * - GET /api/downloads
 * - GET /api/user/devices
 */
class PortalSelfServiceController extends Controller
{
    /**
     * 下载中心：已发布更新包列表（公开）
     */
    public function downloads(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);

        $query = UpdatePackage::query()
            ->with(['product:id,name,slug'])
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', (int) $productId);
        }

        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(function (UpdatePackage $pkg) {
            return [
                'id' => $pkg->id,
                'product_id' => $pkg->product_id,
                'product_name' => $pkg->product?->name,
                'version' => $pkg->version,
                'type' => $pkg->type,
                'file_name' => $pkg->file_name,
                'file_size' => $pkg->file_size,
                'file_size_human' => $pkg->fileSizeForHumans(),
                'release_notes' => $pkg->release_notes,
                'published_at' => $pkg->published_at,
                'download_url' => url('/api/updates/'.$pkg->id.'/download'),
            ];
        })->values();

        return ApiResponse::success([
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * 当前登录用户关联客户的设备列表
     */
    public function userDevices(Request $request): JsonResponse
    {
        $user = $request->user();
        $customer = $user->customer;

        if (! $customer) {
            return ApiResponse::success([
                'data' => [],
                'total' => 0,
                'stats' => ['total' => 0, 'active' => 0, 'inactive' => 0],
            ]);
        }

        $licenseIds = $customer->licenses()->pluck('id');

        $query = Device::query()
            ->with(['license:id,license_key,product_id,status'])
            ->where(function ($q) use ($customer, $licenseIds, $user) {
                $q->whereIn('license_id', $licenseIds);
                if ($user->tenant_id) {
                    $q->orWhere(function ($inner) use ($user, $customer) {
                        $inner->where('tenant_id', $user->tenant_id)
                            ->whereHas('license', fn ($lq) => $lq->where('customer_id', $customer->id));
                    });
                }
            });

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('fingerprint', 'like', "%{$search}%")
                    ->orWhere('platform', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $paginator = $query->orderByDesc('last_seen_at')->paginate($perPage);

        $base = Device::query()->whereIn('license_id', $licenseIds);
        $total = (clone $base)->count();
        $active = (clone $base)->whereNotNull('license_id')->where('is_blacklisted', false)->count();

        $items = collect($paginator->items())->map(function (Device $device) {
            return [
                'id' => $device->id,
                'fingerprint' => $device->fingerprint,
                'platform' => $device->platform,
                'os_version' => $device->os_version,
                'hostname' => $device->metadata['hostname'] ?? null,
                'name' => $device->metadata['name'] ?? null,
                'license_id' => $device->license_id,
                'license' => $device->license,
                'license_key' => $device->license?->license_key,
                'is_active' => ! $device->is_blacklisted && $device->license_id !== null,
                'last_seen_at' => $device->last_seen_at,
                'created_at' => $device->created_at,
                'lifecycle_stage' => $device->lifecycle_stage,
                'trust_score' => $device->trust_score,
            ];
        })->values();

        return ApiResponse::success([
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'stats' => [
                'total' => $total,
                'active' => $active,
                'inactive' => max(0, $total - $active),
            ],
        ]);
    }
}

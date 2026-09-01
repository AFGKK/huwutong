<?php

namespace App\Http\Controllers\Api;

use App\Enums\LicenseStatus;
use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Services\DeviceLimiter;
use App\Services\FingerprintMatcher;
use App\Services\FingerprintService;
use App\Services\GeoFenceService;
use App\Services\HoneypotService;
use App\Services\IpRestrictionService;
use App\Services\LicenseService;
use App\Services\TimeRestrictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivateController extends Controller
{
    public function __construct(
        protected LicenseService      $licenseService,
        protected FingerprintService  $fingerprintService,
        protected FingerprintMatcher  $fingerprintMatcher,
        protected DeviceLimiter       $deviceLimiter,
        protected TimeRestrictionService $timeRestriction,
        protected IpRestrictionService $ipRestriction,
        protected GeoFenceService     $geoFence,
        protected HoneypotService     $honeypotService,
    ) {}

    /**
     * 在线激活 License
     *
     * POST /api/license/activate
     */
    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_key' => 'required|string',
            'product_id' => 'nullable|integer|exists:products,id',
            'sku_id' => 'nullable|integer|exists:product_skus,id',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'fingerprint' => 'required|string',
            'components' => 'nullable|array',
            'components.mac' => 'nullable|string',
            'components.cpu_id' => 'nullable|string',
            'components.motherboard' => 'nullable|string',
            'components.disk_sn' => 'nullable|string',
            'components.system_uuid' => 'nullable|string',
            'platform' => 'nullable|string',
            'os_version' => 'nullable|string',
            'device_name' => 'nullable|string|max:100',
            'metadata' => 'nullable|array',
        ]);

        // A5: 小程序激活用 openid 生成稳定指纹（同一微信账号 = 同一设备维度）
        $authUser = $request->user();
        if (
            $authUser
            && ($data['platform'] ?? '') === 'wechat_miniprogram'
            && ! empty($authUser->wechat_openid)
        ) {
            $data['fingerprint'] = 'wx_mp_' . substr(hash('sha256', $authUser->wechat_openid), 0, 24);
        }

        // 查找 License
        $license = License::where('license_key', $data['license_key'])->first();

        // M2-03 蜜罐检测：如果 License 不存在，检查是否为蜜罐密钥
        if (! $license) {
            $honeypot = $this->honeypotService->detect($data['license_key']);
            if ($honeypot) {
                $this->honeypotService->handleTrigger($honeypot, $request->ip(), [
                    'fingerprint' => $data['fingerprint'],
                    'platform' => $data['platform'] ?? null,
                    'metadata' => $data['metadata'] ?? [],
                ]);

                // 返回伪造的成功响应，让攻击者以为激活成功
                return ApiResponse::success([
                    'activated' => true,
                    'license_key' => $data['license_key'],
                    'message' => __('app.api.activate.ok'),
                    'expires_at' => now()->addYear()->toIso8601String(),
                    'device_id' => 'hny-' . substr(md5($data['fingerprint']), 0, 12),
                ]);
            }

            return ApiResponse::error('LICENSE_NOT_FOUND', __('app.api.activate.key_not_found'), 404);
        }

        // 产品匹配：未传 product_id 时默认使用 License 绑定产品（小程序/公开激活场景）
        if ($license->product_id) {
            $reqProductId = $data['product_id'] ?? $license->product_id;
            if ($license->product_id != $reqProductId) {
                return ApiResponse::error('LICENSE_PRODUCT_MISMATCH', __('app.api.activate.product_mismatch'), 422);
            }
            $data['product_id'] = $reqProductId;
        }

        // SKU 匹配：未传时默认使用 License 绑定 SKU
        if ($license->sku_id) {
            $reqSkuId = $data['sku_id'] ?? $license->sku_id;
            if ($license->sku_id != $reqSkuId) {
                return ApiResponse::error('LICENSE_SKU_MISMATCH', __('app.api.activate.sku_mismatch'), 422);
            }
            $data['sku_id'] = $reqSkuId;
        }

        // 租户隔离：未传时默认使用 License 所属租户
        if ($license->tenant_id) {
            $reqTenantId = $data['tenant_id'] ?? $license->tenant_id;
            if ($license->tenant_id != $reqTenantId) {
                return ApiResponse::error('LICENSE_TENANT_MISMATCH', __('app.api.activate.tenant_mismatch'), 422);
            }
            $data['tenant_id'] = $reqTenantId;
        }

        // 状态校验（含漏洞3修复：suspended/frozen 不可激活新设备）
        $status = LicenseStatus::tryFrom($license->status);
        if (! $status) {
            return ApiResponse::error('LICENSE_STATUS_INVALID', __('app.api.activate.status_invalid'), 422);
        }
        if ($status !== LicenseStatus::Pending && $status !== LicenseStatus::Active) {
            return ApiResponse::error('LICENSE_NOT_ACTIVATABLE', __('app.api.activate.not_activatable', ['status' => $license->status]), 422);
        }

        // 过期检查
        if ($license->expires_at && $license->expires_at->isPast()) {
            return ApiResponse::error('LICENSE_EXPIRED', __('app.api.activate.expired'), 422);
        }

        // M3-77 时段限制检查
        $timeCheck = $this->timeRestriction->check($license, $request->ip());
        if (! $timeCheck['allowed']) {
            return ApiResponse::error(
                'LICENSE_TIME_RESTRICTED',
                $timeCheck['reason'],
                403,
                ['time_restriction' => $timeCheck]
            );
        }

        // M2-92 IP 范围限制
        if (config('license-restrictions.ip_restriction.enabled', true)
            && config('license-restrictions.ip_restriction.check_on_activate', true)) {
            $ipCheck = $this->ipRestriction->check((int) $license->id, (string) $request->ip(), 'activate');
            if (! ($ipCheck['allowed'] ?? true)) {
                return ApiResponse::error(
                    'LICENSE_IP_RESTRICTED',
                    $ipCheck['reason'] ?? __('app.api.activate.ip_restricted'),
                    403,
                    ['ip_restriction' => $ipCheck]
                );
            }
        }

        // M2-93 地理围栏
        if (config('license-restrictions.geo_fence.enabled', true)
            && config('license-restrictions.geo_fence.check_on_activate', true)) {
            $geoCheck = $this->geoFence->check((int) $license->id, (string) $request->ip(), 'activate');
            if (! ($geoCheck['allowed'] ?? true)) {
                return ApiResponse::error(
                    'DEVICE_REGION_BLOCKED',
                    $geoCheck['reason'] ?? __('app.api.activate.region_blocked'),
                    403,
                    ['geo_fence' => $geoCheck]
                );
            }
        }

        // 先将 License 从 pending 转为 active
        if ($license->status === LicenseStatus::Pending->value) {
            $license = $this->licenseService->activate($license);
        }

        // 设备处理
        $device = Device::where('license_id', $license->id)
            ->where('fingerprint', $data['fingerprint'])
            ->first();

        if (! $device && ! empty($data['components'])) {
            $incomingComponents = $data['components'];
            $existingDevices = Device::where('license_id', $license->id)->get();

            foreach ($existingDevices as $existingDevice) {
                $storedComponents = $existingDevice->metadata['components'] ?? null;
                if (! $storedComponents) continue;

                if ($this->fingerprintMatcher->isMatch($storedComponents, $incomingComponents)) {
                    $device = $existingDevice;
                    break;
                }
            }
        }

        if (! $device) {
            $limitResult = $this->deviceLimiter->acquire(
                $license,
                $data['fingerprint'],
                $license->max_devices,
            );

            if (! $limitResult->allowed) {
                $this->deviceLimiter->release($license);
                return ApiResponse::error(
                    'DEVICE_LIMIT_EXCEEDED',
                    __('app.api.activate.device_limit', ['max' => $license->max_devices]),
                    422,
                    ['max_devices' => $license->max_devices, 'current_count' => $limitResult->currentCount],
                );
            }

            $device = Device::create([
                'tenant_id' => $license->tenant_id,
                'license_id' => $license->id,
                'fingerprint' => $data['fingerprint'],
                'platform' => $data['platform'] ?? null,
                'os_version' => $data['os_version'] ?? null,
                'metadata' => [
                    'components' => $data['components'] ?? [],
                    'client' => $data['metadata'] ?? [],
                    'device_name' => $data['device_name'] ?? null,
                ],
            ]);

            $this->deviceLimiter->refreshDeviceCount($license);
            $this->deviceLimiter->release($license);
        } else {
            $device->update([
                'platform' => $data['platform'] ?? $device->platform,
                'os_version' => $data['os_version'] ?? $device->os_version,
                'last_seen_at' => now(),
            ]);
        }

        // 记录激活
        $activation = DB::transaction(function () use ($license, $device, $request, $data) {
            return LicenseActivation::create([
                'license_id' => $license->id,
                'device_id' => $device->id,
                'ip_address' => $request->ip(),
                'fingerprint' => $data['fingerprint'],
                'action' => 'activate',
                'payload' => [
                    'components' => $data['components'] ?? null,
                    'platform' => $data['platform'] ?? null,
                    'os_version' => $data['os_version'] ?? null,
                    'metadata' => $data['metadata'] ?? null,
                    'device_name' => $data['device_name'] ?? null,
                    'user_id' => $request->user()?->id,
                ],
            ]);
        });

        return ApiResponse::success([
            'valid' => true,
            'license_key' => $license->license_key,
            'status' => $license->status,
            'expires_at' => $license->expires_at,
            'activation_id' => $activation->id,
            'device_id' => $device->id,
            'is_existing_device' => $device->wasRecentlyCreated === false,
        ], __('app.api.activate.ok'));
    }

    /**
     * 验证 License
     */
    public function validate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_key' => 'required|string',
            'fingerprint' => 'nullable|string',
        ]);

        $license = License::where('license_key', $data['license_key'])->first();

        // M2-03 蜜罐检测
        if (! $license) {
            $honeypot = $this->honeypotService->detect($data['license_key']);
            if ($honeypot) {
                $this->honeypotService->handleTrigger($honeypot, $request->ip(), [
                    'action' => 'validate',
                    'fingerprint' => $data['fingerprint'] ?? null,
                ]);

                // 返回伪造的有效响应
                return ApiResponse::success([
                    'valid' => true,
                    'license_key' => $data['license_key'],
                    'status' => 'active',
                    'expires_at' => now()->addYear()->toIso8601String(),
                    'max_devices' => 999,
                ]);
            }

            return ApiResponse::error('LICENSE_NOT_FOUND', __('app.api.activate.key_not_found'), 404);
        }

        $result = $this->licenseService->validate($license);

        return ApiResponse::success($result);
    }
}

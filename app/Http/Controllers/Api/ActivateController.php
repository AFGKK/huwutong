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
use App\Services\HoneypotService;
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
            'fingerprint' => 'required|string',
            'components' => 'nullable|array',
            'components.mac' => 'nullable|string',
            'components.cpu_id' => 'nullable|string',
            'components.motherboard' => 'nullable|string',
            'components.disk_sn' => 'nullable|string',
            'components.system_uuid' => 'nullable|string',
            'platform' => 'nullable|string',
            'os_version' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

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
                    'message' => '激活成功',
                    'expires_at' => now()->addYear()->toIso8601String(),
                    'device_id' => 'hny-' . substr(md5($data['fingerprint']), 0, 12),
                ]);
            }

            return ApiResponse::error('LICENSE_NOT_FOUND', 'License Key 不存在', 404);
        }

        // 状态校验
        $status = LicenseStatus::tryFrom($license->status);
        if (! $status || ! $status->isActivable()) {
            return ApiResponse::error('LICENSE_NOT_ACTIVATABLE', "License 当前状态「{$license->status}」不允许激活", 422);
        }

        // 过期检查
        if ($license->expires_at && $license->expires_at->isPast()) {
            return ApiResponse::error('LICENSE_EXPIRED', 'License 已过期', 422);
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
                    "设备数量已达上限 ({$license->max_devices})",
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
        ], '激活成功');
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

            return ApiResponse::error('LICENSE_NOT_FOUND', 'License Key 不存在', 404);
        }

        $result = $this->licenseService->validate($license);

        return ApiResponse::success($result);
    }
}

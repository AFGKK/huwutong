<?php

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Events\LicenseStatusChanged;
use App\Models\License;
use App\Models\LicenseActivation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LicenseService
{
    public function __construct(
        protected TimeRestrictionService $timeRestriction,
        protected IpRestrictionService $ipRestriction,
        protected GeoFenceService $geoFence,
    ) {}

    /**
     * 安全地变更 License 状态（强制执行转移矩阵校验）
     *
     * @throws ValidationException
     */
    public function transitionStatus(License $license, LicenseStatus $newStatus, ?string $reason = null): License
    {
        $currentStatus = LicenseStatus::tryFrom($license->status);

        if (! $currentStatus) {
            throw new \RuntimeException(__("app.license.msg_6ed9117f"));
        }

        // 校验状态转移是否合法
        if (! $currentStatus->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "不允许从「{$currentStatus->value}」转移到「{$newStatus->value}」",
            ]);
        }

        $oldStatus = $license->status;

        DB::transaction(function () use ($license, $newStatus, $reason) {
            $license->update([
                'status' => $newStatus->value,
            ]);

            // 激活时记录激活时间
            if ($newStatus === LicenseStatus::Active && ! $license->activated_at) {
                $license->update(['activated_at' => now()]);
            }
        });

        // 触发状态变更事件
        event(new LicenseStatusChanged($license, $oldStatus, $newStatus->value, $reason));

        return $license->fresh();
    }

    /**
     * 激活 License（pending → active）
     */
    public function activate(License $license, array $activationData = []): License
    {
        return $this->transitionStatus(
            $license,
            LicenseStatus::Active,
            $activationData['reason'] ?? null,
        );
    }

    /**
     * 挂起 License（active/suspended → suspended）
     */
    public function suspend(License $license, ?string $reason = null): License
    {
        return $this->transitionStatus($license, LicenseStatus::Suspended, $reason);
    }

    /**
     * 冻结 License（active/suspended/frozen → frozen）
     */
    public function freeze(License $license, ?string $reason = null): License
    {
        return $this->transitionStatus($license, LicenseStatus::Frozen, $reason);
    }

    /**
     * 恢复 License（suspended/frozen → active）
     */
    public function restore(License $license, ?string $reason = null): License
    {
        return $this->transitionStatus($license, LicenseStatus::Active, $reason);
    }

    /**
     * 过期 License（active/suspended/frozen/expired → expired）
     */
    public function expire(License $license, ?string $reason = null): License
    {
        return $this->transitionStatus($license, LicenseStatus::Expired, $reason);
    }

    /**
     * 续费（expired → active）
     */
    public function renew(License $license, ?string $reason = null): License
    {
        return $this->transitionStatus($license, LicenseStatus::Active, $reason);
    }

    /**
     * 撤销 License（非终态 → revoked）
     */
    public function revoke(License $license, ?string $reason = null): License
    {
        return $this->transitionStatus($license, LicenseStatus::Revoked, $reason);
    }

    /**
     * 退款（active/suspended/frozen/expired → refunded）
     */
    public function refund(License $license, ?string $reason = null): License
    {
        return $this->transitionStatus($license, LicenseStatus::Refunded, $reason);
    }

    /**
     * 加入黑名单（任何状态 → blacklisted，终态）
     */
    public function blacklist(License $license, ?string $reason = null): License
    {
        return $this->transitionStatus($license, LicenseStatus::Blacklisted, $reason);
    }

    /**
     * 校验 License 在当前时刻是否有效
     */
    public function validate(License $license): array
    {
        $status = LicenseStatus::tryFrom($license->status);

        $result = [
            'valid' => false,
            'license_key' => $license->license_key,
            'status' => $license->status,
            'message' => '',
        ];

        if (! $status) {
            $result['message'] = '无效的状态';
            return $result;
        }

        if (! $status->isUsable()) {
            $result['message'] = match ($license->status) {
                'pending' => 'License 尚未激活',
                'expired' => 'License 已过期',
                'revoked' => 'License 已被撤销',
                'refunded' => 'License 已退款',
                'blacklisted' => 'License 已被列入黑名单',
                default => 'License 当前不可用',
            };
            return $result;
        }

        // 检查是否过期
        if ($license->expires_at && $license->expires_at->isPast()) {
            $result['message'] = 'License 已过期';
            return $result;
        }

        // M3-77 时段限制检查
        $timeCheck = $this->timeRestriction->check($license, request()->ip());
        if (! $timeCheck['allowed']) {
            $result['message'] = $timeCheck['reason'];
            $result['error_code'] = 'LICENSE_TIME_RESTRICTED';
            $result['time_restriction'] = $timeCheck;
            return $result;
        }

        $clientIp = (string) (request()->ip() ?? '');

        // M2-92 IP 范围限制
        if ($clientIp !== ''
            && config('license-restrictions.ip_restriction.enabled', true)
            && config('license-restrictions.ip_restriction.check_on_validate', true)) {
            $ipCheck = $this->ipRestriction->check((int) $license->id, $clientIp, 'validate');
            if (! ($ipCheck['allowed'] ?? true)) {
                $result['message'] = $ipCheck['reason'] ?? 'IP 不在允许范围';
                $result['error_code'] = 'LICENSE_IP_RESTRICTED';
                $result['ip_restriction'] = $ipCheck;
                return $result;
            }
        }

        // M2-93 地理围栏
        if ($clientIp !== ''
            && config('license-restrictions.geo_fence.enabled', true)
            && config('license-restrictions.geo_fence.check_on_validate', true)) {
            $geoCheck = $this->geoFence->check((int) $license->id, $clientIp, 'validate');
            if (! ($geoCheck['allowed'] ?? true)) {
                $result['message'] = $geoCheck['reason'] ?? '当前地区不允许使用';
                $result['error_code'] = 'DEVICE_REGION_BLOCKED';
                $result['geo_fence'] = $geoCheck;
                return $result;
            }
        }

        // 检查设备数量是否超限
        $activeDeviceCount = $license->devices()->count();
        if ($activeDeviceCount >= $license->max_devices) {
            $result['message'] = "设备数量已达上限 ({$license->max_devices})";
            return $result;
        }

        $result['valid'] = true;
        $result['message'] = 'License 有效';

        return $result;
    }

    /**
     * 获取 License 的状态分析信息
     */
    public function getStatusInfo(License $license): array
    {
        $status = LicenseStatus::tryFrom($license->status);

        $availableTransitions = [];
        if ($status) {
            foreach (LicenseStatus::cases() as $target) {
                if ($status->canTransitionTo($target)) {
                    $availableTransitions[] = $target->value;
                }
            }
        }

        return [
            'current_status' => $license->status,
            'is_usable' => $status?->isUsable() ?? false,
            'is_terminal' => $status?->isTerminal() ?? false,
            'is_expired' => $license->expires_at ? $license->expires_at->isPast() : false,
            'available_transitions' => $availableTransitions,
            'device_count' => $license->devices()->count(),
            'max_devices' => $license->max_devices,
        ];
    }

    /**
     * 创建新的 License
     */
    public function create(array $data): License
    {
        return DB::transaction(function () use ($data) {
            $license = License::create([
                'tenant_id' => $data['tenant_id'],
                'product_id' => $data['product_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'license_key' => $data['license_key'],
                'type' => $data['type'] ?? 'standard',
                'status' => LicenseStatus::Pending->value,
                'expires_at' => $data['expires_at'] ?? null,
                'seats' => $data['seats'] ?? 1,
                'max_devices' => $data['max_devices'] ?? 1,
                'metadata' => $data['metadata'] ?? null,
            ]);

            return $license;
        });
    }

    /**
     * 更新 License 信息（不可变更状态）
     */
    public function update(License $license, array $data): License
    {
        return DB::transaction(function () use ($license, $data) {
            $updatable = array_intersect_key($data, array_flip([
                'product_id', 'customer_id', 'type',
                'expires_at', 'seats', 'max_devices', 'metadata',
            ]));

            $license->update($updatable);

            return $license->fresh();
        });
    }

    /**
     * 软删除 License（保留在回收站）
     */
    public function softDelete(License $license): void
    {
        DB::transaction(function () use ($license) {
            $license->delete();
        });
    }

    /**
     * 从回收站恢复软删除的 License
     */
    public function restoreFromTrash(int $id): License
    {
        return DB::transaction(function () use ($id) {
            $license = License::withTrashed()->findOrFail($id);
            $license->restore();
            return $license->fresh();
        });
    }

    /**
     * 获取 License 统计（按状态和类型分布）
     */
    public function stats(?int $tenantId = null): array
    {
        $query = License::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $total = (clone $query)->count();

        $byStatus = (clone $query)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byType = (clone $query)
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $active = (clone $query)
            ->where('status', LicenseStatus::Active->value)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->count();

        $expiringSoon = (clone $query)
            ->where('status', LicenseStatus::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->count();

        $expired = (clone $query)
            ->where(function ($q) {
                $q->where('status', LicenseStatus::Expired->value)
                  ->orWhere(function ($q2) {
                      $q2->where('status', LicenseStatus::Active->value)
                         ->whereNotNull('expires_at')
                         ->where('expires_at', '<=', now());
                  });
            })
            ->count();

        return [
            'total' => $total,
            'active' => $active,
            'expired' => $expired,
            'expiring_soon' => $expiringSoon,
            'by_status' => $byStatus,
            'by_type' => $byType,
        ];
    }
}

<?php

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Models\Customer;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrialLicenseService
{
    /**
     * Trial 默认配置
     */
    const int DEFAULT_TRIAL_DAYS = 14;
    const int DEFAULT_MAX_DEVICES = 2;
    const int DEFAULT_SEATS = 1;

    /**
     * 防滥用：同一客户/设备允许的最大 Trial 次数
     */
    const int MAX_TRIALS_PER_CUSTOMER = 1;
    const int TRIAL_COOLDOWN_DAYS = 90; // 同一客户再次 Trial 的冷却期

    public function __construct(
        protected LicenseService $licenseService,
        protected KeyGenerator   $keyGenerator,
    ) {}

    /**
     * 创建 Trial 试用 License
     *
     * @param Tenant   $tenant    租户
     * @param Customer $customer  客户
     * @param Product  $product   产品
     * @param int      $trialDays 试用天数（默认 14）
     * @return License
     *
     * @throws ValidationException
     */
    public function createTrial(
        Tenant   $tenant,
        Customer $customer,
        Product  $product,
        int      $trialDays = self::DEFAULT_TRIAL_DAYS,
    ): License {

        // 防滥用检查
        $this->validateTrialEligibility($customer);

        $licenseKey = $this->keyGenerator->generate('trial');

        return DB::transaction(function () use ($tenant, $customer, $product, $licenseKey, $trialDays) {
            $license = $this->licenseService->create([
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'customer_id' => $customer->id,
                'license_key' => $licenseKey,
                'type' => 'trial',
                'expires_at' => now()->addDays($trialDays),
                'seats' => self::DEFAULT_SEATS,
                'max_devices' => self::DEFAULT_MAX_DEVICES,
                'metadata' => [
                    'trial' => true,
                    'trial_started_at' => now()->toDateTimeString(),
                    'trial_days' => $trialDays,
                    'trial_ends_at' => now()->addDays($trialDays)->toDateTimeString(),
                ],
            ]);

            // 自动激活 Trial
            $this->licenseService->activate($license);

            return $license->fresh();
        });
    }

    /**
     * Trial 一键转正（trial → standard/enterprise）
     *
     * @param License $license   Trial License
     * @param string  $newType   新类型 (standard/enterprise)
     * @param int     $newDays   新有效期（天）
     * @param int     $maxDevices 新设备上限
     * @return License
     */
    public function convertToPaid(
        License $license,
        string  $newType = 'standard',
        int     $newDays = 365,
        int     $maxDevices = 3,
    ): License {

        if ($license->type !== 'trial') {
            throw ValidationException::withMessages([
                'license' => '只有 Trial 类型的 License 可以转正',
            ]);
        }

        if (! in_array($newType, ['standard', 'enterprise', 'development'], true)) {
            throw ValidationException::withMessages([
                'type' => "不支持的类型: {$newType}",
            ]);
        }

        return DB::transaction(function () use ($license, $newType, $newDays, $maxDevices) {
            // 更新为付费类型
            $license->update([
                'type' => $newType,
                'expires_at' => now()->addDays($newDays),
                'max_devices' => $maxDevices,
                'metadata' => array_merge($license->metadata ?? [], [
                    'converted_from_trial' => true,
                    'converted_at' => now()->toDateTimeString(),
                    'previous_type' => 'trial',
                ]),
            ]);

            return $license->fresh();
        });
    }

    /**
     * 检查 Trial 是否即将过期/已过期，执行对应操作
     *
     * @param License $license
     * @return array 操作结果
     */
    public function checkTrialStatus(License $license): array
    {
        if ($license->type !== 'trial') {
            return ['action' => 'none', 'message' => '非 Trial License'];
        }

        $now = now();
        $expiresAt = $license->expires_at;
        $daysRemaining = $now->diffInDays($expiresAt, false); // 负数表示已过期

        // 已过期
        if ($daysRemaining < 0) {
            if ($license->status === LicenseStatus::Active->value) {
                $this->licenseService->expire($license, 'Trial 试用期结束');
            }
            return [
                'action' => 'expired',
                'message' => 'Trial 已过期',
                'days_remaining' => (int) $daysRemaining,
            ];
        }

        // 即将过期（3 天内提醒）
        if ($daysRemaining <= 3) {
            return [
                'action' => 'expiring_soon',
                'message' => "Trial 将在 {$daysRemaining} 天后过期",
                'days_remaining' => (int) $daysRemaining,
            ];
        }

        return [
            'action' => 'active',
            'message' => "Trial 进行中，剩余 {$daysRemaining} 天",
            'days_remaining' => (int) $daysRemaining,
        ];
    }

    /**
     * 批量检查所有即将过期的 Trial（用于定时任务）
     */
    public function checkExpiringTrials(): array
    {
        $results = [];
        $now = now();

        // 获取即将过期（3天内）的激活 Trial
        $expiringTrials = License::where('type', 'trial')
            ->where('status', LicenseStatus::Active->value)
            ->whereBetween('expires_at', [$now, $now->copy()->addDays(3)])
            ->get();

        foreach ($expiringTrials as $trial) {
            $results[$trial->id] = $this->checkTrialStatus($trial);
        }

        return $results;
    }

    /**
     * 批量处理所有已过期的 Trial（用于定时任务）
     */
    public function expireOverdueTrials(): array
    {
        $results = [];
        $now = now();

        $overdueTrials = License::where('type', 'trial')
            ->where('status', LicenseStatus::Active->value)
            ->where('expires_at', '<', $now)
            ->get();

        foreach ($overdueTrials as $trial) {
            $this->licenseService->expire($trial, 'Trial 试用期结束自动过期');
            $results[$trial->id] = [
                'license_key' => $trial->license_key,
                'action' => 'expired',
            ];
        }

        return $results;
    }

    /**
     * 防滥用检查
     *
     * @throws ValidationException
     */
    protected function validateTrialEligibility(Customer $customer): void
    {
        // 同一客户已创建过 Trial
        $existingTrials = License::where('customer_id', $customer->id)
            ->where('type', 'trial')
            ->count();

        if ($existingTrials >= self::MAX_TRIALS_PER_CUSTOMER) {
            throw ValidationException::withMessages([
                'customer' => '该客户已使用过试用',
            ]);
        }

        // 检查冷却期：该客户最近是否有过 Trial
        $recentTrial = License::where('customer_id', $customer->id)
            ->where('type', 'trial')
            ->where('created_at', '>', now()->subDays(self::TRIAL_COOLDOWN_DAYS))
            ->first();

        if ($recentTrial) {
            throw ValidationException::withMessages([
                'customer' => '试用冷却期未过，请 ' . now()->diffInDays($recentTrial->created_at->addDays(self::TRIAL_COOLDOWN_DAYS)) . ' 天后重试',
            ]);
        }
    }
}

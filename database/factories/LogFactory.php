<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Device;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LogFactory extends Factory
{
    private static array $actions = [
        'license.created', 'license.status_changed', 'license.activated',
        'license.deactivated', 'license.expired', 'license.renewed',
        'device.activated', 'device.deactivated', 'device.replaced',
        'user.login', 'user.logout', 'user.login_failed',
        'user.password_changed', 'user.created', 'user.updated',
        'customer.created', 'customer.updated',
        'product.created', 'product.updated',
        'api_key.created', 'api_key.revoked',
        'security.mfa_enabled', 'security.mfa_disabled',
        'system.config_updated', 'system.backup_created',
    ];

    private static array $types = ['audit', 'security', 'error', 'system'];

    public function definition(): array
    {
        $action = fake()->randomElement(self::$actions);
        $type = str_starts_with($action, 'security') ? 'security'
            : (str_starts_with($action, 'system') ? 'system' : 'audit');
        $description = $this->generateDescription($action);

        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'license_id' => fake()->boolean(30) ? License::factory() : null,
            'customer_id' => fake()->boolean(30) ? Customer::factory() : null,
            'device_id' => fake()->boolean(20) ? Device::factory() : null,
            'product_id' => fake()->boolean(20) ? Product::factory() : null,
            'type' => $type,
            'action' => $action,
            'description' => $description,
            'payload' => $this->generatePayload($action),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => fake()->dateTimeBetween('-90 days', 'now'),
        ];
    }

    /**
     * 指定类型
     */
    public function ofType(string $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    /**
     * 指定动作
     */
    public function ofAction(string $action): static
    {
        return $this->state(fn () => [
            'action' => $action,
            'type' => str_starts_with($action, 'security') ? 'security' : 'audit',
            'description' => $this->generateDescription($action),
            'payload' => $this->generatePayload($action),
        ]);
    }

    private function generateDescription(string $action): string
    {
        return match (true) {
            str_contains($action, 'license.created') => '创建 License [' . strtoupper(fake()->bothify('??####')) . ']',
            str_contains($action, 'license.status_changed') => 'License 状态变更: active → suspended',
            str_contains($action, 'device.activated') => '设备激活于 License',
            str_contains($action, 'device.deactivated') => '设备从 License 解绑',
            str_contains($action, 'user.login') => '用户登录系统',
            str_contains($action, 'user.login_failed') => '用户登录失败（密码错误）',
            str_contains($action, 'security') => '安全事件: ' . fake()->sentence(3),
            str_contains($action, 'system') => '系统配置已更新',
            default => fake()->sentence(4),
        };
    }

    private function generatePayload(string $action): array
    {
        return match (true) {
            str_contains($action, 'license.status_changed') => [
                'old_status' => 'active',
                'new_status' => 'suspended',
                'reason' => fake()->optional(0.5)->sentence(),
            ],
            str_contains($action, 'device.activated'), str_contains($action, 'device.deactivated') => [
                'fingerprint' => strtoupper(fake()->md5()),
            ],
            str_contains($action, 'user.login_failed') => [
                'attempts' => fake()->numberBetween(1, 5),
                'reason' => '密码错误',
            ],
            default => [],
        };
    }
}

<?php

namespace Database\Seeders;

use App\Models\RateLimitRule;
use Illuminate\Database\Seeder;

class RateLimitRuleSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'name' => '激活API限流',
                'slug' => 'activate',
                'key_type' => 'ip',
                'max_attempts' => 30,
                'window_seconds' => 60,
                'priority' => 0,
                'is_active' => true,
                'description' => 'License 激活 API，每 IP 每分钟 30 次',
            ],
            [
                'name' => '激活API-产品限流',
                'slug' => 'activate-product',
                'key_type' => 'product',
                'max_attempts' => 500,
                'window_seconds' => 3600,
                'priority' => 5,
                'is_active' => true,
                'description' => 'License 激活 API，每产品每小时 500 次',
            ],
            [
                'name' => '激活API-License限流',
                'slug' => 'activate-license',
                'key_type' => 'license',
                'max_attempts' => 10,
                'window_seconds' => 60,
                'priority' => 10,
                'is_active' => true,
                'description' => 'License 激活 API，每 License Key 每分钟 10 次',
            ],
            [
                'name' => '验证API限流',
                'slug' => 'validate',
                'key_type' => 'ip',
                'max_attempts' => 60,
                'window_seconds' => 60,
                'priority' => 0,
                'is_active' => true,
                'description' => 'License 验证 API，每 IP 每分钟 60 次',
            ],
            [
                'name' => '验证API-License限流',
                'slug' => 'validate-license',
                'key_type' => 'license',
                'max_attempts' => 30,
                'window_seconds' => 60,
                'priority' => 10,
                'is_active' => true,
                'description' => 'License 验证 API，每 License Key 每分钟 30 次',
            ],
            [
                'name' => '通用API限流',
                'slug' => 'api',
                'key_type' => 'ip',
                'max_attempts' => 100,
                'window_seconds' => 60,
                'priority' => 0,
                'is_active' => true,
                'description' => '通用 API，每 IP 每分钟 100 次',
            ],
            [
                'name' => '通用API-租户限流',
                'slug' => 'api-tenant',
                'key_type' => 'tenant',
                'max_attempts' => 1000,
                'window_seconds' => 60,
                'priority' => 5,
                'is_active' => true,
                'description' => '通用 API，每租户每分钟 1000 次',
            ],
            [
                'name' => '管理API限流',
                'slug' => 'admin',
                'key_type' => 'ip',
                'max_attempts' => 200,
                'window_seconds' => 60,
                'priority' => 0,
                'is_active' => true,
                'description' => '管理 API，每 IP 每分钟 200 次',
            ],
            [
                'name' => '管理API-租户限流',
                'slug' => 'admin-tenant',
                'key_type' => 'tenant',
                'max_attempts' => 2000,
                'window_seconds' => 60,
                'priority' => 5,
                'is_active' => true,
                'description' => '管理 API，每租户每分钟 2000 次',
            ],
            [
                'name' => '默认限流',
                'slug' => 'default',
                'key_type' => 'ip',
                'max_attempts' => 60,
                'window_seconds' => 60,
                'priority' => 0,
                'is_active' => true,
                'description' => '默认限流规则',
            ],
            [
                'name' => '默认API限流',
                'slug' => 'default-api',
                'key_type' => 'api',
                'max_attempts' => 200,
                'window_seconds' => 60,
                'priority' => 10,
                'is_active' => true,
                'description' => '默认 API 路径级限流',
            ],
            [
                'name' => 'API Key 限流',
                'slug' => 'apikey',
                'key_type' => 'api_key',
                'max_attempts' => 500,
                'window_seconds' => 60,
                'priority' => 0,
                'is_active' => true,
                'description' => '每 API Key 每分钟 500 次',
            ],
        ];

        foreach ($defaults as $rule) {
            RateLimitRule::firstOrCreate(
                ['slug' => $rule['slug']],
                $rule
            );
        }
    }
}

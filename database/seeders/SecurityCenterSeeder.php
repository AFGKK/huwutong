<?php

namespace Database\Seeders;

use App\Models\LoginPolicy;
use Illuminate\Database\Seeder;

class SecurityCenterSeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            ['name' => '最大登录尝试次数', 'policy_key' => 'max_attempts', 'value_type' => 'integer', 'value' => '5', 'description' => '超过此次数后账户将被临时锁定', 'sort_order' => 1],
            ['name' => '锁定时间(分钟)', 'policy_key' => 'lockout_duration', 'value_type' => 'integer', 'value' => '30', 'description' => '账户锁定后自动解锁时间', 'sort_order' => 2],
            ['name' => '密码最小长度', 'policy_key' => 'password_min_length', 'value_type' => 'integer', 'value' => '8', 'description' => '用户密码最低字符数要求', 'sort_order' => 3],
            ['name' => '密码需大写字母', 'policy_key' => 'password_require_uppercase', 'value_type' => 'boolean', 'value' => 'true', 'description' => '密码必须包含至少一个大写字母', 'sort_order' => 4],
            ['name' => '密码需数字', 'policy_key' => 'password_require_numbers', 'value_type' => 'boolean', 'value' => 'true', 'description' => '密码必须包含至少一个数字', 'sort_order' => 5],
            ['name' => '密码需特殊字符', 'policy_key' => 'password_require_symbols', 'value_type' => 'boolean', 'value' => 'false', 'description' => '密码必须包含至少一个特殊字符', 'sort_order' => 6],
            ['name' => '强制 MFA', 'policy_key' => 'mfa_required', 'value_type' => 'boolean', 'value' => 'false', 'description' => '所有用户必须启用多因素认证', 'sort_order' => 7],
            ['name' => '会话超时(分钟)', 'policy_key' => 'session_timeout_minutes', 'value_type' => 'integer', 'value' => '480', 'description' => '会话无活动后自动过期时间', 'sort_order' => 8],
            ['name' => '会话绝对超时(小时)', 'policy_key' => 'session_absolute_timeout', 'value_type' => 'integer', 'value' => '24', 'description' => '会话最长时间，到期强制重新登录', 'sort_order' => 9],
            ['name' => '单设备登录', 'policy_key' => 'session_single_device', 'value_type' => 'boolean', 'value' => 'false', 'description' => '同一用户只允许一个活跃会话', 'sort_order' => 10],
            ['name' => '强制 IP 白名单', 'policy_key' => 'ip_whitelist_enforced', 'value_type' => 'boolean', 'value' => 'false', 'description' => '启用后将只允许白名单中的 IP 访问', 'sort_order' => 11],
            ['name' => '地域限制(JSON)', 'policy_key' => 'geo_restriction', 'value_type' => 'json', 'value' => '[]', 'description' => '允许登录的地理位置列表', 'sort_order' => 12],
        ];

        foreach ($policies as $policy) {
            LoginPolicy::updateOrCreate(
                ['policy_key' => $policy['policy_key'], 'tenant_id' => null],
                $policy
            );
        }

        $this->command->info('已创建 ' . count($policies) . ' 条默认登录安全策略');
    }
}

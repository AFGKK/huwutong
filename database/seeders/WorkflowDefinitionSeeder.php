<?php

namespace Database\Seeders;

use App\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;

class WorkflowDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        WorkflowDefinition::create([
            'name' => 'renewal_pipeline',
            'description' => '续费流水线：创建发票 → 处理支付 → 延长订阅 → 延长 License',
            'steps_definition' => [
                ['name' => 'create_invoice', 'timeout' => 120, 'retry_max' => 3, 'retry_delay' => [30, 60, 120]],
                ['name' => 'process_payment', 'timeout' => 300, 'retry_max' => 3, 'retry_delay' => [60, 300, 600]],
                ['name' => 'extend_subscription', 'timeout' => 30, 'retry_max' => 2, 'retry_delay' => [10, 30]],
                ['name' => 'extend_licenses', 'timeout' => 60, 'retry_max' => 2, 'retry_delay' => [10, 30]],
            ],
            'is_active' => true,
        ]);

        WorkflowDefinition::create([
            'name' => 'license_expiry',
            'description' => 'License 过期处理：标记过期 → 禁用功能 → 发送 Webhook',
            'steps_definition' => [
                ['name' => 'expire_license', 'timeout' => 60, 'retry_max' => 2, 'retry_delay' => [10, 60]],
                ['name' => 'disable_feature_flags', 'timeout' => 60, 'retry_max' => 2, 'retry_delay' => [10, 30]],
                ['name' => 'send_expiry_webhook', 'timeout' => 30, 'retry_max' => 3, 'retry_delay' => [5, 10, 30]],
            ],
            'is_active' => true,
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\ImportMappingTemplate;
use Illuminate\Database\Seeder;

class DataImportSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // License 导入
            [
                'name' => '标准 License 导入',
                'entity_type' => 'licenses',
                'is_system' => true,
                'sort_order' => 1,
                'mappings' => [
                    ['source_field' => 'license_key', 'target_field' => 'license_key', 'target_label' => 'License Key', 'is_required' => true, 'is_identifier' => true],
                    ['source_field' => 'product_id', 'target_field' => 'product_id', 'target_label' => '产品 ID', 'is_required' => true],
                    ['source_field' => 'customer_id', 'target_field' => 'customer_id', 'target_label' => '客户 ID'],
                    ['source_field' => 'status', 'target_field' => 'status', 'target_label' => '状态'],
                    ['source_field' => 'expires_at', 'target_field' => 'expires_at', 'target_label' => '过期时间'],
                    ['source_field' => 'max_activations', 'target_field' => 'max_activations', 'target_label' => '最大激活数'],
                    ['source_field' => 'notes', 'target_field' => 'notes', 'target_label' => '备注'],
                ],
                'default_options' => ['update_existing' => true, 'skip_errors' => false, 'batch_size' => 100],
            ],
            // 客户导入
            [
                'name' => '标准客户导入',
                'entity_type' => 'customers',
                'is_system' => true,
                'sort_order' => 2,
                'mappings' => [
                    ['source_field' => 'name', 'target_field' => 'name', 'target_label' => '名称', 'is_required' => true],
                    ['source_field' => 'email', 'target_field' => 'email', 'target_label' => '邮箱', 'is_required' => true, 'is_identifier' => true],
                    ['source_field' => 'phone', 'target_field' => 'phone', 'target_label' => '电话'],
                    ['source_field' => 'company', 'target_field' => 'company', 'target_label' => '公司'],
                    ['source_field' => 'address', 'target_field' => 'address', 'target_label' => '地址'],
                    ['source_field' => 'notes', 'target_field' => 'notes', 'target_label' => '备注'],
                ],
                'default_options' => ['update_existing' => true, 'skip_errors' => false, 'batch_size' => 200],
            ],
            // 订阅导入
            [
                'name' => '标准订阅导入',
                'entity_type' => 'subscriptions',
                'is_system' => true,
                'sort_order' => 3,
                'mappings' => [
                    ['source_field' => 'customer_id', 'target_field' => 'customer_id', 'target_label' => '客户 ID', 'is_required' => true],
                    ['source_field' => 'product_id', 'target_field' => 'product_id', 'target_label' => '产品 ID', 'is_required' => true],
                    ['source_field' => 'amount', 'target_field' => 'amount', 'target_label' => '金额', 'is_required' => true],
                    ['source_field' => 'currency', 'target_field' => 'currency', 'target_label' => '币种'],
                    ['source_field' => 'billing_cycle', 'target_field' => 'billing_cycle', 'target_label' => '计费周期'],
                    ['source_field' => 'status', 'target_field' => 'status', 'target_label' => '状态'],
                    ['source_field' => 'starts_at', 'target_field' => 'starts_at', 'target_label' => '开始时间'],
                    ['source_field' => 'ends_at', 'target_field' => 'ends_at', 'target_label' => '结束时间'],
                ],
                'default_options' => ['update_existing' => true, 'skip_errors' => false],
            ],
            // 产品导入
            [
                'name' => '标准产品导入',
                'entity_type' => 'products',
                'is_system' => true,
                'sort_order' => 4,
                'mappings' => [
                    ['source_field' => 'name', 'target_field' => 'name', 'target_label' => '产品名称', 'is_required' => true],
                    ['source_field' => 'slug', 'target_field' => 'slug', 'target_label' => '标识', 'is_required' => true, 'is_identifier' => true],
                    ['source_field' => 'description', 'target_field' => 'description', 'target_label' => '描述'],
                    ['source_field' => 'price', 'target_field' => 'price', 'target_label' => '价格'],
                    ['source_field' => 'status', 'target_field' => 'status', 'target_label' => '状态'],
                ],
                'default_options' => ['update_existing' => true],
            ],
            // 工单导入
            [
                'name' => '标准工单导入',
                'entity_type' => 'tickets',
                'is_system' => true,
                'sort_order' => 5,
                'mappings' => [
                    ['source_field' => 'title', 'target_field' => 'title', 'target_label' => '标题', 'is_required' => true],
                    ['source_field' => 'description', 'target_field' => 'description', 'target_label' => '描述'],
                    ['source_field' => 'customer_id', 'target_field' => 'customer_id', 'target_label' => '客户 ID', 'is_required' => true],
                    ['source_field' => 'priority', 'target_field' => 'priority', 'target_label' => '优先级'],
                    ['source_field' => 'status', 'target_field' => 'status', 'target_label' => '状态'],
                ],
                'default_options' => ['update_existing' => false, 'skip_errors' => true],
            ],
        ];

        foreach ($templates as $tpl) {
            ImportMappingTemplate::updateOrCreate(
                ['name' => $tpl['name'], 'entity_type' => $tpl['entity_type']],
                $tpl
            );
        }

        $this->command->info('已创建 ' . count($templates) . ' 个预设映射模板');
    }
}

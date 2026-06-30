<?php

namespace Database\Seeders;

use App\Models\SlaContract;
use App\Models\SlaMetric;
use Illuminate\Database\Seeder;

class SlaSeeder extends Seeder
{
    public function run(): void
    {
        if (SlaContract::where('slug', 'standard-sla-template')->exists()) {
            $this->command->info('SLA templates already seeded, skipping.');
            return;
        }

        // ─── 标准 SLA 模板 ───
        $standard = SlaContract::create([
            'name' => '标准 SLA 模板',
            'slug' => 'standard-sla-template',
            'description' => '适用于大多数客户的默认 SLA 标准',
            'level' => 'standard',
            'scope' => ['modules' => ['tickets', 'support'], 'channels' => ['email', 'portal']],
            'terms' => [
                'response_time' => 120,
                'resolution_time' => 480,
                'availability' => 99.5,
            ],
            'penalties' => ['credits' => 5, 'discount' => 0],
            'business_hours' => [
                'timezone' => 'Asia/Shanghai',
                'workdays' => [1, 2, 3, 4, 5],
                'hours_start' => '09:00',
                'hours_end' => '18:00',
            ],
            'effective_date' => now()->format('Y-m-d'),
            'is_active' => true,
            'is_template' => true,
        ]);

        SlaMetric::insert([
            [
                'sla_contract_id' => $standard->id,
                'metric_key' => 'response_time',
                'name' => '首次响应时间',
                'unit' => 'minutes',
                'target_value' => 120,
                'warning_threshold' => 80,
                'measurement_window' => 'monthly',
                'data_source' => 'tickets',
                'sort_order' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'sla_contract_id' => $standard->id,
                'metric_key' => 'resolution_time',
                'name' => '问题解决时间',
                'unit' => 'minutes',
                'target_value' => 480,
                'warning_threshold' => 80,
                'measurement_window' => 'monthly',
                'data_source' => 'tickets',
                'sort_order' => 2,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'sla_contract_id' => $standard->id,
                'metric_key' => 'availability',
                'name' => '系统可用性',
                'unit' => 'percentage',
                'target_value' => 99.5,
                'warning_threshold' => 90,
                'measurement_window' => 'monthly',
                'data_source' => 'uptime',
                'sort_order' => 3,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        // ─── 高级 SLA 模板 ───
        $premium = SlaContract::create([
            'name' => '高级 SLA 模板',
            'slug' => 'premium-sla-template',
            'description' => '适用于 VIP 客户的高级 SLA',
            'level' => 'premium',
            'scope' => ['modules' => ['tickets', 'support', 'api'], 'channels' => ['email', 'portal', 'phone']],
            'terms' => [
                'response_time' => 30,
                'resolution_time' => 240,
                'availability' => 99.9,
            ],
            'penalties' => ['credits' => 10, 'discount' => 5],
            'business_hours' => [
                'timezone' => 'Asia/Shanghai',
                'workdays' => [1, 2, 3, 4, 5, 6],
                'hours_start' => '08:00',
                'hours_end' => '22:00',
            ],
            'effective_date' => now()->format('Y-m-d'),
            'is_active' => true,
            'is_template' => true,
        ]);

        SlaMetric::insert([
            [
                'sla_contract_id' => $premium->id,
                'metric_key' => 'response_time',
                'name' => '首次响应时间',
                'unit' => 'minutes',
                'target_value' => 30,
                'warning_threshold' => 85,
                'measurement_window' => 'weekly',
                'data_source' => 'tickets',
                'sort_order' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'sla_contract_id' => $premium->id,
                'metric_key' => 'resolution_time',
                'name' => '问题解决时间',
                'unit' => 'minutes',
                'target_value' => 240,
                'warning_threshold' => 85,
                'measurement_window' => 'weekly',
                'data_source' => 'tickets',
                'sort_order' => 2,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'sla_contract_id' => $premium->id,
                'metric_key' => 'availability',
                'name' => '系统可用性',
                'unit' => 'percentage',
                'target_value' => 99.9,
                'warning_threshold' => 95,
                'measurement_window' => 'monthly',
                'data_source' => 'uptime',
                'sort_order' => 3,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'sla_contract_id' => $premium->id,
                'metric_key' => 'ticket_backlog',
                'name' => '工单积压控制',
                'unit' => 'count',
                'target_value' => 5,
                'warning_threshold' => 80,
                'measurement_window' => 'daily',
                'data_source' => 'tickets',
                'sort_order' => 4,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $this->command->info('Seeded SLA templates: standard + premium.');
    }
}

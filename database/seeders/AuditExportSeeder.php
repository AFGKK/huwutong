<?php

namespace Database\Seeders;

use App\Models\AuditArchivePolicy;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AuditExportSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 默认归档策略 ───
        $policies = [
            [
                'name' => '审计日志归档策略',
                'type' => 'audit',
                'archive_after_days' => 90,
                'delete_after_days' => 365,
                'archive_disk' => 'local',
                'compress_archive' => true,
                'is_active' => true,
                'description' => '审计日志：90天归档，1年清理',
            ],
            [
                'name' => '安全日志归档策略',
                'type' => 'security',
                'archive_after_days' => 180,
                'delete_after_days' => 730,
                'archive_disk' => 'local',
                'compress_archive' => true,
                'is_active' => true,
                'description' => '安全日志：180天归档，2年清理',
            ],
            [
                'name' => '错误日志归档策略',
                'type' => 'error',
                'archive_after_days' => 30,
                'delete_after_days' => 180,
                'archive_disk' => 'local',
                'compress_archive' => false,
                'is_active' => true,
                'description' => '错误日志：30天归档，180天清理',
            ],
            [
                'name' => '系统日志归档策略',
                'type' => 'system',
                'archive_after_days' => 60,
                'delete_after_days' => 365,
                'archive_disk' => 'local',
                'compress_archive' => true,
                'is_active' => true,
                'description' => '系统日志：60天归档，1年清理',
            ],
        ];

        foreach ($policies as $policy) {
            AuditArchivePolicy::updateOrCreate(
                ['type' => $policy['type']],
                $policy
            );
        }

        $this->command->info('已创建 ' . count($policies) . ' 条默认归档策略');
    }
}

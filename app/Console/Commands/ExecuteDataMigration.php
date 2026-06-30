<?php

namespace App\Console\Commands;

use App\Models\DataMigration;
use App\Services\DataResidencyService;
use Illuminate\Console\Command;

/**
 * 执行数据迁移 (M3-60)
 *
 * php artisan data:residency-migrate {migration_id}
 */
class ExecuteDataMigration extends Command
{
    protected $signature = 'data:residency-migrate {migration_id? : 迁移ID, 留空执行所有待定迁移}';
    protected $description = '执行数据本地化迁移';

    public function handle(DataResidencyService $service): int
    {
        $migrationId = $this->argument('migration_id');

        if ($migrationId) {
            $migration = DataMigration::find($migrationId);
            if (!$migration) {
                $this->error("迁移 #{$migrationId} 不存在");
                return self::FAILURE;
            }
            return $this->executeSingle($service, $migration);
        }

        // 执行所有待定迁移
        $pending = DataMigration::where('status', 'pending')->get();
        if ($pending->isEmpty()) {
            $this->info('没有待处理的迁移');
            return self::SUCCESS;
        }

        $this->info("发现 {$pending->count()} 个待处理迁移");
        foreach ($pending as $migration) {
            $this->executeSingle($service, $migration);
        }

        return self::SUCCESS;
    }

    protected function executeSingle(DataResidencyService $service, DataMigration $migration): int
    {
        $this->info("执行迁移 #{$migration->id}: {$migration->source_region} → {$migration->target_region} ({$migration->data_classification})");

        $bar = $this->output->createProgressBar(1);
        $bar->start();

        $result = $service->executeMigration($migration->id);

        $bar->finish();
        $this->newLine();

        if ($result->status === 'completed') {
            $this->info("✅ 迁移完成: 处理 {$result->processed_items}/{$result->total_items} 文件");
            return self::SUCCESS;
        }

        $this->error("❌ 迁移失败");
        return self::FAILURE;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\DataAnonymizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AnonymizeDataExport extends Command
{
    protected $signature = 'data:anonymize-export
        {--connection= : 源数据库连接（默认主库）}
        {--staging= : Staging 数据库连接}
        {--tables=* : 指定表（可多次使用，默认全部非排除表）}
        {--preview : 预览模式（仅显示匿名化效果，不写入Staging）}
        {--preview-table= : 预览模式下指定表}
        {--preview-limit=10 : 预览行数}';

    protected $description = '生产数据匿名化并导出到 Staging 环境 (M2-139)';

    public function handle(DataAnonymizationService $service): int
    {
        if ($this->option('preview')) {
            return $this->handlePreview($service);
        }

        $this->info('开始数据匿名化导出流水线...');
        $this->newLine();

        $sourceConnection = $this->option('connection') ?? config('database.default');
        $targetConnection = $this->option('staging') ?? config('data-anonymization.staging_connection');
        $tables = $this->option('tables') ?: null;

        $this->line("源数据库: {$sourceConnection}");
        $this->line("目标数据库: {$targetConnection}");

        if ($tables) {
            $this->line("指定表: " . implode(', ', $tables));
        } else {
            $exportable = $service->getExportableTables($sourceConnection);
            $this->line("自动检测表: " . count($exportable) . " 个");
        }

        $this->newLine();

        if (! $this->confirm('确定要执行数据匿名化导出？此操作将覆盖目标数据库的数据。')) {
            $this->info('已取消');
            return Command::FAILURE;
        }

        $bar = $this->output->createProgressBar(100);

        try {
            $task = $service->runPipeline(
                null,
                $sourceConnection,
                $targetConnection,
                $tables,
            );

            $bar->finish();
            $this->newLine(2);

            if ($task->isFailed()) {
                $this->error('导出失败: ' . $task->error_message);
                return Command::FAILURE;
            }

            $this->info('数据匿名化导出完成！');
            $this->table(
                ['指标', '值'],
                [
                    ['任务 ID', (string) $task->id],
                    ['总记录数', (string) $task->total_records],
                    ['已处理记录', (string) $task->processed_records],
                    ['匿名化表数', (string) count($task->anonymized_tables ?? [])],
                    ['状态', $task->status],
                ]
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('导出异常: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function handlePreview(DataAnonymizationService $service): int
    {
        $table = $this->option('preview-table');
        $limit = (int) $this->option('preview-limit');

        if (! $table) {
            $this->error('预览模式需要指定 --preview-table');
            return Command::FAILURE;
        }

        $this->info("预览表 {$table} 的匿名化效果（{$limit} 行）:");
        $this->newLine();

        $connection = $this->option('connection') ?? config('database.default');

        try {
            $rows = \Illuminate\Support\Facades\DB::connection($connection)
                ->table($table)
                ->limit($limit)
                ->get()
                ->toArray();

            if (empty($rows)) {
                $this->warn("表 {$table} 没有数据");
                return Command::SUCCESS;
            }

            $data = array_map(fn($r) => (array) $r, $rows);
            $anonymized = $service->anonymizeData($data, $table);
            $rules = $service->getAnonymizationRules($table);

            $this->line("匿名化规则:");
            foreach ($rules as $field => $method) {
                $this->line("  - {$field}: {$method}");
            }

            $this->newLine();
            $this->info("原始 vs 匿名化 对比:");

            $headers = array_keys((array) $rows[0]);
            $this->table(
                array_merge($headers, array_map(fn($h) => $h . '(匿名)', $headers)),
                array_map(function ($orig, $anon) use ($headers) {
                    return array_merge(
                        array_map(fn($h) => Str::limit($orig[$h] ?? '', 30), $headers),
                        array_map(fn($h) => Str::limit($anon[$h] ?? '', 30), $headers),
                    );
                }, $data, $anonymized)
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('预览失败: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

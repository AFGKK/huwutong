<?php

namespace App\Services;

use App\Models\DataAnonymizationRule;
use App\Models\DataExportTask;
use App\Support\DbSql;
use Faker\Factory as FakerFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 数据匿名化导出流水线服务 (M2-139)
 *
 * 从生产数据库导出数据 → 匿名化敏感字段 → 导入 Staging 数据库。
 * 支持自定义匿名化规则、表过滤、批量处理。
 */
class DataAnonymizationService
{
    /**
     * Faker 实例（中文）
     */
    protected \Faker\Generator $faker;

    /**
     * 固定值替换文本
     */
    protected string $fixedValue;

    /**
     * 批次大小
     */
    protected int $chunkSize;

    public function __construct()
    {
        $this->faker = FakerFactory::create('zh_CN');
        $this->fixedValue = config('data-anonymization.fixed_value', '[ANONYMIZED]');
        $this->chunkSize = (int) config('data-anonymization.chunk_size', 500);
    }

    /**
     * 运行完整的导出→匿名化→导入流水线
     *
     * @param string|null $taskId 指定任务 ID（不指定则创建新任务）
     * @param string|null $sourceConnection 源数据库连接
     * @param string|null $targetConnection 目标数据库连接
     * @param array|null $tables 要处理的表列表（null=全部非排除表）
     * @return DataExportTask
     */
    public function runPipeline(
        ?string $taskId = null,
        ?string $sourceConnection = null,
        ?string $targetConnection = null,
        ?array $tables = null,
    ): DataExportTask {
        $sourceConnection = $sourceConnection ?? config('database.default');
        $targetConnection = $targetConnection ?? config('data-anonymization.staging_connection', 'mysql_staging');

        // 查找或创建任务
        $task = $taskId ? DataExportTask::find($taskId) : null;
        if (! $task) {
            $task = DataExportTask::create([
                'type' => DataExportTask::TYPE_EXPORT,
                'status' => DataExportTask::STATUS_PENDING,
                'source_connection' => $sourceConnection,
                'target_connection' => $targetConnection,
                'tables' => $tables,
                'excluded_tables' => config('data-anonymization.exclude_tables', []),
            ]);
        }

        $task->markAsRunning();

        try {
            $tables = $tables ?? $this->getExportableTables($sourceConnection);
            $excludeTables = config('data-anonymization.exclude_tables', []);
            $schemaOnlyTables = config('data-anonymization.schema_only_tables', []);
            $truncateTables = config('data-anonymization.truncate_tables', []);

            // 过滤排除表
            $tables = array_filter($tables, fn($t) => ! in_array($t, $excludeTables));
            $dataTables = array_filter($tables, fn($t) => ! in_array($t, $schemaOnlyTables));
            $totalTables = count($tables);

            $totalRecords = 0;
            $processedRecords = 0;
            $anonymizedTables = [];
            $excludedLog = [];

            // 阶段1: 复制表结构
            foreach ($tables as $table) {
                $this->copyTableStructure($sourceConnection, $targetConnection, $table);
            }

            // 阶段2: 对于 schema_only 和 truncate 表特殊处理
            foreach ($schemaOnlyTables as $table) {
                // 结构已在阶段1复制，不导数据
                $excludedLog[] = $table . '(schema_only)';
            }

            // 阶段3: 导出、匿名化、导入数据表
            foreach ($dataTables as $table) {
                if (in_array($table, $truncateTables)) {
                    // 只创建结构，不导数据（已在阶段1创建）
                    DB::connection($targetConnection)->statement("DELETE FROM {$table}");
                    $excludedLog[] = $table . '(truncated)';
                    continue;
                }

                $count = DB::connection($sourceConnection)->table($table)->count();
                $totalRecords += $count;

                if ($count === 0) {
                    $anonymizedTables[] = $table;
                    $task->updateProgress($processedRecords);
                    continue;
                }

                // 获取表的匿名化规则
                $rules = $this->getAnonymizationRules($table);

                // 清空目标表
                DB::connection($targetConnection)->statement("TRUNCATE TABLE {$table}");

                // 分批处理
                DB::connection($sourceConnection)->table($table)
                    ->orderBy('id')
                    ->chunk($this->chunkSize, function ($rows) use ($table, $rules, $targetConnection, &$processedRecords) {
                        $insertData = [];
                        foreach ($rows as $row) {
                            $insertData[] = $this->anonymizeRow((array) $row, $rules);
                        }

                        foreach (array_chunk($insertData, 100) as $chunk) {
                            DB::connection($targetConnection)->table($table)->insert($chunk);
                        }

                        $processedRecords += count($insertData);
                    });

                $anonymizedTables[] = $table;
                $task->updateProgress($processedRecords);
            }

            // 更新任务完成状态
            $task->markAsCompleted();
            $task->update([
                'total_records' => $totalRecords,
                'anonymized_tables' => $anonymizedTables,
                'excluded_tables' => $excludedLog,
            ]);

            Log::info('数据匿名化导出流水线完成', [
                'task_id' => $task->id,
                'total_tables' => $totalTables,
                'data_tables' => count($dataTables),
                'total_records' => $totalRecords,
                'anonymized_tables' => count($anonymizedTables),
            ]);

        } catch (\Throwable $e) {
            $task->markAsFailed($e->getMessage());
            Log::error('数据匿名化导出流水线失败', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $task->fresh();
    }

    /**
     * 仅执行匿名化（不涉及 Staging 导入），返回匿名化后的数据集
     *
     * @param array $data 原始数据
     * @param string $tableName 表名（用于匹配规则）
     * @return array 匿名化后的数据
     */
    public function anonymizeData(array $data, string $tableName): array
    {
        $rules = $this->getAnonymizationRules($tableName);
        $result = [];

        foreach ($data as $row) {
            $result[] = $this->anonymizeRow($row, $rules);
        }

        return $result;
    }

    /**
     * 获取可导出的表列表（非排除表）
     */
    public function getExportableTables(string $connection = null): array
    {
        $connection = $connection ?? config('database.default');
        $excludeTables = config('data-anonymization.exclude_tables', []);
        $schemaOnlyTables = config('data-anonymization.schema_only_tables', []);
        $truncateTables = config('data-anonymization.truncate_tables', []);

        $allExcluded = array_merge($excludeTables, $schemaOnlyTables, $truncateTables);

        $tableNames = DbSql::listTableNames($connection);

        return array_values(array_filter($tableNames, fn ($t) => ! in_array($t, $allExcluded)));
    }

    /**
     * 获取指定表的所有活跃匿名化规则（合并默认 + 自定义）
     */
    public function getAnonymizationRules(string $tableName): array
    {
        $defaultRules = config("data-anonymization.default_rules.{$tableName}", []);
        $customRules = DataAnonymizationRule::getActiveRulesForTable($tableName);

        // 自定义规则覆盖默认规则
        $merged = $defaultRules;
        foreach ($customRules as $field => $rule) {
            $merged[$field] = $rule['method'];
        }

        return $merged;
    }

    /**
     * 获取所有支持匿名化的表列表（含规则摘要）
     */
    public function getSupportedTables(): array
    {
        $defaultRules = config('data-anonymization.default_rules', []);
        $result = [];

        foreach ($defaultRules as $table => $fields) {
            $customRules = DataAnonymizationRule::getActiveRulesForTable($table);
            $fieldCount = count($fields) + count($customRules);

            $result[] = [
                'table' => $table,
                'anonymized_fields' => $fieldCount,
                'has_custom_rules' => count($customRules) > 0,
            ];
        }

        return $result;
    }

    /**
     * 复制表结构从源到目标
     */
    protected function copyTableStructure(string $sourceConnection, string $targetConnection, string $table): void
    {
        $createSql = DB::connection($sourceConnection)->select("SHOW CREATE TABLE {$table}");
        $createRow = $createSql[0] ?? null;
        if (! $createRow) {
            return;
        }

        $createTableSql = $createRow->{'Create Table'} ?? current((array) $createRow);
        if (! $createTableSql) {
            return;
        }

        // 先删除目标表（如果存在）
        DB::connection($targetConnection)->statement("DROP TABLE IF EXISTS {$table}");

        // 创建表结构
        DB::connection($targetConnection)->statement($createTableSql);
    }

    /**
     * 对单行数据进行匿名化处理
     */
    protected function anonymizeRow(array $row, array $rules): array
    {
        $anonymized = [];
        foreach ($row as $field => $value) {
            if ($value === null || ! isset($rules[$field])) {
                $anonymized[$field] = $value;
                continue;
            }

            $method = $rules[$field];
            $anonymized[$field] = $this->applyAnonymization($method, $value);
        }

        return $anonymized;
    }

    /**
     * 应用匿名化方法到单个值
     */
    protected function applyAnonymization(string $method, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return match ($method) {
            'chinese_name' => $this->faker->name(),
            'email' => $this->faker->email(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'postal_code' => $this->faker->postcode(),
            'company_name' => $this->faker->company(),
            'job_title' => $this->faker->jobTitle(),
            'domain' => $this->faker->domainName(),
            'sentence' => $this->faker->sentence(mt_rand(3, 8)),
            'paragraph' => $this->faker->paragraph(mt_rand(2, 5)),
            'token' => $this->faker->md5(),
            'ip' => $this->faker->ipv4(),
            'fixed_value' => $this->fixedValue,
            'url' => 'https://example.com/' . Str::random(16),
            'uuid' => (string) Str::uuid(),
            default => $this->faker->word(),
        };
    }

    /**
     * 数据预览（原始 vs 匿名化对比）
     */
    public function preview(string $table, int $limit = 5): array
    {
        $rows = DB::table($table)->limit($limit)->get()->map(fn($r) => (array) $r)->toArray();
        $rules = $this->getAnonymizationRules($table);
        $anonymized = array_map(fn($row) => $this->anonymizeRow($row, $rules), $rows);
        return [
            'table' => $table,
            'original' => $rows,
            'anonymized' => $anonymized,
            'rules_applied' => $rules,
        ];
    }

    /**
     * 规则列表
     */
    public function list(\Illuminate\Http\Request $request): array
    {
        return DataAnonymizationRule::when($request->filled('table'), fn($q) => $q->where('table_name', $request->table))
            ->orderByDesc('id')->paginate(20)->toArray();
    }

    /**
     * 创建规则
     */
    public function create(array $data): DataAnonymizationRule
    {
        return DataAnonymizationRule::create($data);
    }

    /**
     * 显示规则
     */
    public function show(DataAnonymizationRule $rule): DataAnonymizationRule
    {
        return $rule;
    }

    /**
     * 更新规则
     */
    public function update(DataAnonymizationRule $rule, array $data): DataAnonymizationRule
    {
        $rule->update($data);
        return $rule->fresh();
    }

    /**
     * 删除规则
     */
    public function delete(DataAnonymizationRule $rule): void
    {
        $rule->delete();
    }

    /**
     * 任务列表
     */
    public function getTasks(\Illuminate\Http\Request $request): array
    {
        return DataExportTask::orderByDesc('id')->paginate(20)->toArray();
    }

    /**
     * 任务详情
     */
    public function getTaskDetail(int $id): ?DataExportTask
    {
        return DataExportTask::findOrFail($id);
    }

    /**
     * 重试任务
     */
    public function retryTask(int $id): DataExportTask
    {
        $task = DataExportTask::findOrFail($id);
        if ($task->status === 'failed') {
            $task->update(['status' => 'pending', 'error_message' => null, 'processed_records' => 0]);
        }
        return $task->fresh();
    }
}

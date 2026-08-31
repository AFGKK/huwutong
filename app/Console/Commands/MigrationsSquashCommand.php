<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * D-M1: 迁移文件整理与合并
 *
 * 功能：
 *   1. `--list` — 列出当前所有迁移文件（按月份分组统计）
 *   2. `--report` — 分析迁移文件中的表创建/修改操作
 *   3. `--backup` — 备份当前迁移文件到 database/migrations_backup/
 *   4. `--squash` — 将 2026 年 6 月及之前的迁移合并为一个大的基准迁移文件
 *   5. `--check` — 检查数据库中哪些迁移已执行
 *
 * php artisan migrations:squash --list
 * php artisan migrations:squash --report
 * php artisan migrations:squash --backup
 * php artisan migrations:squash --squash
 * php artisan migrations:squash --check
 */
class MigrationsSquashCommand extends Command
{
    protected $signature = 'migrations:squash
        {--list : 列出迁移文件统计}
        {--report : 分析迁移中的表操作}
        {--backup : 备份迁移文件}
        {--squash : 合并 2026-06 及之前的迁移为基准文件}
        {--check : 检查数据库中已执行的迁移}';

    protected $description = 'D-M1: 迁移文件整理、统计与合并';

    protected string $migrationsDir;

    protected string $backupDir;

    public function __construct()
    {
        parent::__construct();
        $this->migrationsDir = database_path('migrations');
        $this->backupDir = database_path('migrations_backup');
    }

    public function handle(): int
    {
        $mode = $this->determineMode();

        return match ($mode) {
            'list' => $this->listMigrations(),
            'report' => $this->reportAnalysis(),
            'backup' => $this->backupMigrations(),
            'squash' => $this->squashMigrations(),
            'check' => $this->checkExecuted(),
            default => $this->showHelp(),
        };
    }

    protected function determineMode(): string
    {
        foreach (['list', 'report', 'backup', 'squash', 'check'] as $opt) {
            if ($this->option($opt)) {
                return $opt;
            }
        }
        return 'help';
    }

    protected function showHelp(): int
    {
        $this->info('=== D-M1: 迁移文件整理工具 ===');
        $this->newLine();
        $this->line('  php artisan migrations:squash --list    列出迁移文件统计');
        $this->line('  php artisan migrations:squash --report  分析迁移中的表操作');
        $this->line('  php artisan migrations:squash --backup  备份迁移文件');
        $this->line('  php artisan migrations:squash --squash  合并 2026-06 及之前的迁移');
        $this->line('  php artisan migrations:squash --check   检查已执行的迁移');

        return self::SUCCESS;
    }

    protected function listMigrations(): int
    {
        $files = File::files($this->migrationsDir);
        $phpFiles = array_filter($files, fn ($f) => $f->getExtension() === 'php');

        $this->info("=== 迁移文件统计 (总计: ".count($phpFiles)." 个) ===");
        $this->newLine();

        // 按年月分组
        $groups = [];
        foreach ($phpFiles as $file) {
            $prefix = substr($file->getFilename(), 0, 7);
            $groups[$prefix][] = $file->getFilename();
        }
        ksort($groups);

        $this->table(
            ['年月', '文件数', '示例文件'],
            array_map(fn ($prefix, $files) => [
                $prefix,
                count($files),
                $files[0],
            ], array_keys($groups), $groups)
        );

        $this->newLine();
        $this->line("提示: 执行 --squash 可将 2026-06 及之前的文件合并");
        $this->line("      执行 --backup 可先备份到 migrations_backup/");

        return self::SUCCESS;
    }

    protected function reportAnalysis(): int
    {
        $files = File::files($this->migrationsDir);
        $phpFiles = array_filter($files, fn ($f) => $f->getExtension() === 'php');

        $createTables = [];
        $alterTables = [];
        $operations = [];

        foreach ($phpFiles as $file) {
            $content = File::get($file->getPathname());

            // 提取 Schema::create 的表名
            preg_match_all('/Schema::create\(\'(\w+)\'/i', $content, $creates);
            foreach ($creates[1] as $table) {
                $createTables[$table][] = $file->getFilename();
            }

            // 提取 Schema::table 的表名
            preg_match_all('/Schema::table\(\'(\w+)\'/i', $content, $alters);
            foreach ($alters[1] as $table) {
                $alterTables[$table][] = $file->getFilename();
            }

            // 统计操作类型
            if (!empty($creates[1])) $operations['Schema::create'] = ($operations['Schema::create'] ?? 0) + count($creates[1]);
            if (!empty($alters[1])) $operations['Schema::table'] = ($operations['table'] ?? 0) + count($alters[1]);

            if (str_contains($content, 'Schema::drop')) $operations['Schema::drop'] = ($operations['Schema::drop'] ?? 0) + 1;
            if (str_contains($content, 'Schema::rename')) $operations['Schema::rename'] = ($operations['Schema::rename'] ?? 0) + 1;
            if (str_contains($content, 'DB::statement')) $operations['DB::statement'] = ($operations['DB::statement'] ?? 0) + 1;
            if (str_contains($content, "\\n")) $operations['raw_sql'] = ($operations['raw_sql'] ?? 0) + 1;
        }

        $this->info("=== 迁移操作分析 ===");
        $this->newLine();

        $this->line("操作类型分布:");
        $this->table(
            ['操作', '次数'],
            array_map(fn ($op, $count) => [$op, $count], array_keys($operations), array_values($operations))
        );

        $this->newLine();
        $this->line("创建的表 (".count($createTables)." 个):");
        $this->table(
            ['表名', '来自迁移文件'],
            array_map(fn ($table, $files) => [$table, implode(', ', $files)], array_keys($createTables), $createTables)
        );

        $this->newLine();
        $this->line("修改的表 (".count($alterTables)." 个):");
        $this->table(
            ['表名', '修改次数', '来自迁移文件'],
            array_map(fn ($table, $files) => [$table, count($files), implode(', ', array_slice($files, 0, 3)) . (count($files) > 3 ? '...' : '')], array_keys($alterTables), $alterTables)
        );

        return self::SUCCESS;
    }

    protected function backupMigrations(): int
    {
        if (File::exists($this->backupDir)) {
            if (!$this->confirm("备份目录已存在: {$this->backupDir}。覆盖？")) {
                $this->warn('已取消');
                return self::FAILURE;
            }
            File::deleteDirectory($this->backupDir);
        }

        File::copyDirectory($this->migrationsDir, $this->backupDir);
        $count = count(File::files($this->backupDir));

        $this->info("✅ 已备份 {$count} 个迁移文件到: {$this->backupDir}");

        return self::SUCCESS;
    }

    protected function squashMigrations(): int
    {
        $this->warn('⚠️  此操作会将 2026-06 及之前的迁移合并为一个基准迁移文件。');
        $this->warn('   合并后原有文件将被移入 migrations_archive/ 目录。');
        $this->warn('   建议先执行 --backup 备份。');

        if (!$this->confirm('继续合并？')) {
            $this->warn('已取消');
            return self::FAILURE;
        }

        $files = File::files($this->migrationsDir);
        $phpFiles = array_filter($files, fn ($f) => $f->getExtension() === 'php');

        // 分类：2024年的 + 2026年6月的 = 基准；2026年7月的 = 保留
        $baseFiles = [];
        $keepFiles = [];
        $baseCutoff = '2026-07';

        foreach ($phpFiles as $file) {
            if (str_starts_with($file->getFilename(), $baseCutoff)) {
                $keepFiles[] = $file;
            } else {
                $baseFiles[] = $file;
            }
        }

        // 按文件名排序（保证顺序）
        usort($baseFiles, fn ($a, $b) => strcmp($a->getFilename(), $b->getFilename()));
        usort($keepFiles, fn ($a, $b) => strcmp($a->getFilename(), $b->getFilename()));

        $this->info("将要合并: ".count($baseFiles)." 个迁移文件 (2024 + 2026-06 及之前)");
        $this->info("将保留:   ".count($keepFiles)." 个迁移文件 (2026-07)");

        if (count($baseFiles) === 0) {
            $this->warn('没有可合并的文件');
            return self::SUCCESS;
        }

        if (!$this->confirm("将 {$this->migrationsDir}/migrations_archive/ 目录保存原文件，继续？")) {
            return self::FAILURE;
        }

        // 1. 创建存档目录，移动原文件
        $archiveDir = $this->migrationsDir . '/migrations_archive';
        if (!File::exists($archiveDir)) {
            File::makeDirectory($archiveDir, 0755, true);
        }

        foreach ($baseFiles as $file) {
            File::move($file->getPathname(), $archiveDir . '/' . $file->getFilename());
        }
        $this->info("已移动 ".count($baseFiles)." 个文件到 migrations_archive/");

        // 2. 生成合并后的基准迁移文件
        $squashContent = $this->generateSquashMigration($baseFiles);
        $squashFilename = '2026_06_07_000000_squash_base.php';
        File::put($this->migrationsDir . '/' . $squashFilename, $squashContent);

        $this->info("✅ 已生成基准迁移文件: {$squashFilename}");
        $this->newLine();
        $this->line("当前迁移文件状态:");
        foreach (File::files($this->migrationsDir) as $f) {
            if ($f->getExtension() === 'php') {
                $this->line("  - {$f->getFilename()}");
            }
        }

        $this->newLine();
        $this->warn('⚠️  注意: 新迁移文件需用 --squash 选项配合 migrate:fresh 执行。');
        $this->warn('   如果数据库已有数据，请先备份数据库。');
        $this->line('   验证: php artisan migrate:fresh --seed --pretend 2>&1 | head -30');

        return self::SUCCESS;
    }

    protected function generateSquashMigration(array $files): string
    {
        $now = now();
        $collectedUp = [];
        $collectedDown = [];

        foreach ($files as $file) {
            $content = File::get($file->getPathname());

            // 提取 up() 方法内的内容
            if (preg_match('/function\s+up\s*\(\)\s*:\s*void\s*\{(.*?)\n    \}/s', $content, $upMatch)) {
                $collectedUp[] = "        // --- 来自: {$file->getFilename()} ---\n" . $upMatch[1];
            }

            // 提取 down() 方法内的内容
            if (preg_match('/function\s+down\s*\(\)\s*:\s*void\s*\{(.*?)\n    \}/s', $content, $downMatch)) {
                $collectedDown[] = "        // --- 来自: {$file->getFilename()} ---\n" . $downMatch[1];
            }
        }

        $upBody = implode("\n\n", $collectedUp);
        $downBody = implode("\n\n", $collectedDown);

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * D-M1: 合并基准迁移 — 包含 2024 年至 2026-06 的全部表结构变更。
 *
 * 由 php artisan migrations:squash --squash 于 {$now->toDateTimeString()} 生成。
 * 原始文件存档于 database/migrations/migrations_archive/。
 */
return new class extends Migration
{
    public function up(): void
    {
{$upBody}
    }

    public function down(): void
    {
{$downBody}
    }
};

PHP;
    }

    protected function checkExecuted(): int
    {
        $executed = [];
        try {
            $executed = \DB::table('migrations')->pluck('migration')->toArray();
        } catch (\Throwable $e) {
            $this->error("无法读取 migrations 表: " . $e->getMessage());
            $this->line("提示: 可能数据库未初始化或 migrations 表不存在。");
            return self::FAILURE;
        }

        $files = File::files($this->migrationsDir);
        $phpFiles = array_filter($files, fn ($f) => $f->getExtension() === 'php');

        $executedCount = 0;
        $pendingCount = 0;

        $this->info("=== 迁移执行状态 ===");
        $this->newLine();

        foreach ($phpFiles as $file) {
            $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            if (in_array($name, $executed)) {
                $this->line("  ✅ {$file->getFilename()}");
                $executedCount++;
            } else {
                $this->line("  ⏳ {$file->getFilename()}");
                $pendingCount++;
            }
        }

        $this->newLine();
        $this->info("已执行: {$executedCount} / 待执行: {$pendingCount} / 总计: " . count($phpFiles));

        return self::SUCCESS;
    }
}

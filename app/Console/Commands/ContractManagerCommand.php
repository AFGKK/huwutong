<?php

namespace App\Console\Commands;

use Tests\Contract\PactContract;
use Illuminate\Console\Command;

/**
 * 契约管理命令
 *
 * 用法:
 *   php artisan contract:list          - 列出所有契约
 *   php artisan contract:generate      - 重新生成所有消费者契约
 *   php artisan contract:verify        - 验证所有提供者契约
 *   php artisan contract:diff          - 比较契约变更
 */
class ContractManagerCommand extends Command
{
    protected $signature = 'contract
        {action : list|generate|verify|diff}
        {--consumer= : 指定消费者名称}
        {--provider= : 指定提供者名称}';

    protected $description = 'Pact 契约测试框架管理工具（M1.4-60）';

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listContracts(),
            'generate' => $this->generateContracts(),
            'verify' => $this->verifyContracts(),
            'diff' => $this->diffContracts(),
            default => $this->error("未知操作: {$action}，支持: list|generate|verify|diff"),
        };
    }

    /**
     * 列出所有已存储的契约
     */
    private function listContracts(): int
    {
        $contracts = PactContract::listContracts();

        if (empty($contracts)) {
            $this->warn('没有找到 Pact 契约文件。请先运行: php artisan contract:generate');
            $this->newLine();
            $this->info('或者运行消费者测试自动生成: php artisan test --filter=PhpSdkContractTest');
            return 0;
        }

        $this->info('📋 已注册的 Pact 契约:');
        $this->newLine();

        $rows = [];
        foreach ($contracts as $c) {
            $rows[] = [$c['consumer'], $c['provider'], $c['interactions'], $c['path']];
        }

        $this->table(['消费者', '提供者', '交互数', '文件路径'], $rows);
        $this->newLine();
        $this->info("总计: " . count($contracts) . " 个契约");

        return 0;
    }

    /**
     * 重新生成消费者契约
     */
    private function generateContracts(): int
    {
        $this->info('🔄 重新生成消费者契约...');
        $this->newLine();

        $consumer = $this->option('consumer');
        $provider = $this->option('provider');

        if ($consumer && $provider) {
            // 重新生成指定契约
            $filter = "{$consumer} {$provider}";
            $this->info("重新生成: {$filter}");
        } else {
            // 运行所有消费者测试以生成契约
            $this->info('运行消费者测试...');
        }

        $exitCode = 0;
        $output = [];

        // 运行 PHPUnit 消费者测试
        $cmd = 'php artisan test --filter=PhpSdkContractTest --compact 2>&1';
        exec($cmd, $output, $exitCode);

        foreach ($output as $line) {
            $this->line($line);
        }

        if ($exitCode === 0) {
            $this->newLine();
            $this->info('✅ 消费者契约生成完成');
            $this->listContracts();
        } else {
            $this->error('❌ 消费者契约生成失败');
        }

        return $exitCode;
    }

    /**
     * 验证提供者契约
     */
    private function verifyContracts(): int
    {
        $this->info('🔄 验证提供者契约...');
        $this->newLine();

        $contracts = PactContract::listContracts();

        if (empty($contracts)) {
            $this->warn('没有找到 Pact 契约文件。请先运行: php artisan contract:generate');
            return 0;
        }

        $totalInteractions = 0;
        $passedInteractions = 0;
        $failedInteractions = 0;

        foreach ($contracts as $contract) {
            $this->info("验证: {$contract['consumer']} → {$contract['provider']} ({$contract['interactions']} 个交互)");

            $pact = PactContract::loadFromFile($contract['consumer'], $contract['provider']);
            if (!$pact) continue;

            // 运行 PHPUnit 提供者验证测试
            $cmd = 'php artisan test --filter=LicenseApiProviderContractTest --compact 2>&1';
            $output = [];
            $exitCode = 0;
            exec($cmd, $output, $exitCode);

            foreach ($output as $line) {
                if (str_contains($line, 'FAIL') || str_contains($line, 'ERROR')) {
                    $this->error($line);
                } elseif (str_contains($line, 'PASS') || str_contains($line, 'OK')) {
                    $this->info($line);
                }
            }

            if ($exitCode === 0) {
                $passedInteractions += $contract['interactions'];
            } else {
                $failedInteractions += $contract['interactions'];
            }
        }

        $this->newLine();
        $this->table(
            ['指标', '数值'],
            [
                ['总交互数', $totalInteractions],
                ['通过', $passedInteractions],
                ['失败', $failedInteractions],
            ]
        );

        return $failedInteractions > 0 ? 1 : 0;
    }

    /**
     * 比较契约变更
     */
    private function diffContracts(): int
    {
        $this->info('📊 契约变更分析');
        $this->newLine();

        $contracts = PactContract::listContracts();

        if (empty($contracts)) {
            $this->warn('没有找到 Pact 契约文件');
            return 0;
        }

        // 检查是否有 git 历史版本可比较
        $hasGit = exec('git log --oneline -1 pacts/ 2>&1', $gitOutput, $gitExitCode) === 0;

        if (!$hasGit) {
            $this->warn('未找到 git 历史记录，无法比较变更');
            $this->newLine();
            $this->info('当前契约文件列表:');
            $this->listContracts();
            return 0;
        }

        // 比较当前契约与上次提交的差异
        foreach ($contracts as $contract) {
            $file = $contract['path'];
            $this->info("检查: {$contract['consumer']} → {$contract['provider']}");

            $diffOutput = [];
            exec("git diff HEAD -- {$file} 2>&1", $diffOutput);

            if (empty($diffOutput)) {
                $this->info('  ✅ 无变更');
            } else {
                $this->warn('  ⚠️ 有变更:');
                foreach ($diffOutput as $line) {
                    if (str_starts_with($line, '+') || str_starts_with($line, '-')) {
                        $this->line("    {$line}");
                    }
                }
            }
        }

        return 0;
    }
}

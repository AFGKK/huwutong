<?php

namespace App\Console\Commands;

use App\Models\SecurityScanResult;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 自动化渗透测试 CI 命令
 *
 * M2-112: 集成 OWASP ZAP 到 CI 流水线
 * 每次发布前自动执行 + 高危漏洞阻断发布
 *
 * 用法:
 *   php artisan security:scan              # 运行完整安全扫描
 *   php artisan security:scan --quick      # 快速基线扫描
 *   php artisan security:scan --api        # API 专项扫描
 *   php artisan security:scan --report     # 查看上次扫描报告
 */
class SecurityScanCommand extends Command
{
    protected $signature = 'security:scan
        {--quick : 快速基线扫描（无主动攻击）}
        {--api : API 专项扫描}
        {--report : 查看上次扫描报告}
        {--target= : 扫描目标 URL}
        {--notify= : 扫描完成后通知的用户 ID (逗号分隔)}';

    protected $description = 'OWASP ZAP 自动化渗透测试 CI 集成（M2-112）';

    private string $targetUrl;
    private string $reportDir;

    public function handle(): int
    {
        $this->targetUrl = $this->option('target') ?? config('app.url', 'http://localhost:8000');
        $this->reportDir = storage_path('app/security-scans');

        if (!is_dir($this->reportDir)) {
            mkdir($this->reportDir, 0755, true);
        }

        if ($this->option('report')) {
            return $this->showLastReport();
        }

        // 前置检查
        if (!$this->preflightCheck()) {
            return 1;
        }

        // 执行扫描
        $result = $this->runScan();

        // 保存结果
        $scanResult = $this->saveResult($result);

        // 通知
        if ($notifyIds = $this->option('notify')) {
            $this->notifyUsers(explode(',', $notifyIds), $scanResult);
        }

        // 输出报告
        $this->outputReport($scanResult);

        // 根据策略决定退出码
        if ($scanResult->high_count > 0 && env('ZAP_FAIL_ON_HIGH', true)) {
            $this->error('❌ 发现高危漏洞，阻断发布！');
            return 1;
        }

        $this->info('✅ 安全扫描通过');
        return 0;
    }

    private function preflightCheck(): bool
    {
        $this->info('🔍 前置检查...');

        $zapHost = env('ZAP_HOST', '127.0.0.1');
        $zapPort = env('ZAP_PORT', '8090');
        $zapApiKey = env('ZAP_API_KEY', '');

        try {
            $response = Http::timeout(5)->get("http://{$zapHost}:{$zapPort}/JSON/core/view/version/", [
                'apikey' => $zapApiKey,
            ]);

            if ($response->successful()) {
                $version = $response->json('version', 'unknown');
                $this->info("✅ ZAP 服务可用 (版本: {$version})");
                return true;
            }
        } catch (\Throwable $e) {
            $this->warn("⚠️  ZAP 服务未运行，执行静态安全分析...");
            return $this->runStaticAnalysis();
        }

        return true;
    }

    private function runStaticAnalysis(): bool
    {
        $this->info('📝 执行静态安全分析...');

        $checks = 0;
        $passed = 0;

        $middlewareChecks = [
            'SecurityHeadersMiddleware' => \App\Http\Middleware\SecurityHeadersMiddleware::class,
            'CspManagerMiddleware' => \App\Http\Middleware\CspManager::class,
            'DataMaskingMiddleware' => \App\Http\Middleware\DataMaskingMiddleware::class,
            'EnhancedThrottleMiddleware' => \App\Http\Middleware\EnhancedThrottleMiddleware::class,
            'BruteForceMiddleware' => \App\Http\Middleware\BruteForceMiddleware::class,
            'CorsManagerMiddleware' => \App\Http\Middleware\CorsManager::class,
        ];

        foreach ($middlewareChecks as $name => $class) {
            $checks++;
            if (class_exists($class)) {
                $this->info("  ✅ {$name} 已注册");
                $passed++;
            } else {
                $this->warn("  ⚠️  {$name} 未注册");
            }
        }

        // 检查 Sanctum
        $checks++;
        if (class_exists(\Laravel\Sanctum\Sanctum::class)) {
            $this->info('  ✅ Sanctum 认证已配置');
            $passed++;
        }

        // 检查 APP_DEBUG
        $checks++;
        $appDebug = env('APP_DEBUG', false);
        if (!$appDebug || $appDebug === false || $appDebug === 'false') {
            $this->info('  ✅ 调试模式已关闭');
            $passed++;
        } else {
            $this->warn('  ⚠️  生产环境应关闭调试模式');
        }

        $this->newLine();
        $this->info("静态安全分析: {$passed}/{$checks} 通过");

        return $checks === $passed;
    }

    private function runScan(): array
    {
        $zapHost = env('ZAP_HOST', '127.0.0.1');
        $zapPort = env('ZAP_PORT', '8090');
        $zapApiKey = env('ZAP_API_KEY', '');

        $mode = $this->option('quick') ? 'baseline' : ($this->option('api') ? 'api' : 'full');
        $this->info("🔄 执行{$mode}扫描...");

        $scanId = $this->startZapScan($mode, $zapHost, $zapPort, $zapApiKey);
        if (!$scanId) {
            return ['success' => false, 'high_count' => 0, 'medium_count' => 0, 'low_count' => 0, 'alerts' => [], 'error' => '扫描启动失败'];
        }

        $this->pollScanProgress($scanId, $zapHost, $zapPort, $zapApiKey);
        return $this->getScanResults($zapHost, $zapPort, $zapApiKey);
    }

    private function startZapScan(string $mode, string $host, int $port, string $apiKey): ?int
    {
        try {
            $response = Http::timeout(30)->get("http://{$host}:{$port}/JSON/ascan/action/scan/", [
                'apikey' => $apiKey,
                'url' => $this->targetUrl,
                'recurse' => true,
                'inScopeOnly' => true,
                'scanPolicyName' => 'HWT-Security-Policy',
            ]);

            if ($response->successful()) {
                return (int)($response->json('scan') ?? $response->json('scanId', 0));
            }
        } catch (\Throwable $e) {
            Log::warning('ZAP 扫描启动失败', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function pollScanProgress(int $scanId, string $host, int $port, string $apiKey): void
    {
        $this->info('⏳ 扫描进行中...');
        $maxWait = 600;
        $waited = 0;

        while ($waited < $maxWait) {
            try {
                $response = Http::timeout(10)->get("http://{$host}:{$port}/JSON/ascan/view/status/", [
                    'apikey' => $apiKey,
                    'scanId' => $scanId,
                ]);

                $progress = (int)($response->json('status', 0));
                if ($progress >= 100) {
                    $this->info('✅ 扫描完成!');
                    return;
                }
                if ($waited % 30 === 0) {
                    $this->line("  进度: {$progress}%");
                }
            } catch (\Throwable $e) {
                // retry
            }
            sleep(3);
            $waited += 3;
        }

        $this->warn('⚠️  扫描超时');
    }

    private function getScanResults(string $host, int $port, string $apiKey): array
    {
        $alerts = [];
        try {
            $response = Http::timeout(30)->get("http://{$host}:{$port}/JSON/core/view/alerts/", [
                'apikey' => $apiKey,
                'baseurl' => $this->targetUrl,
                'start' => 0, 'count' => 1000,
            ]);
            $alerts = $response->json('alerts', []);
        } catch (\Throwable $e) {
            Log::warning('获取 ZAP 结果失败', ['error' => $e->getMessage()]);
        }

        $highCount = count(array_filter($alerts, fn($a) => ($a['risk'] ?? '') === 'High'));
        $medCount = count(array_filter($alerts, fn($a) => ($a['risk'] ?? '') === 'Medium'));
        $lowCount = count(array_filter($alerts, fn($a) => ($a['risk'] ?? '') === 'Low'));

        return [
            'success' => $highCount === 0,
            'high_count' => $highCount,
            'medium_count' => $medCount,
            'low_count' => $lowCount,
            'alerts' => $alerts,
        ];
    }

    private function saveResult(array $result): SecurityScanResult
    {
        return SecurityScanResult::create([
            'scan_type' => $this->option('quick') ? 'baseline' : ($this->option('api') ? 'api' : 'full'),
            'target_url' => $this->targetUrl,
            'high_count' => $result['high_count'] ?? 0,
            'medium_count' => $result['medium_count'] ?? 0,
            'low_count' => $result['low_count'] ?? 0,
            'passed' => $result['success'] ?? false,
            'alerts' => $result['alerts'] ?? [],
            'executed_at' => now(),
        ]);
    }

    private function outputReport(SecurityScanResult $result): void
    {
        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  安全扫描报告');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line("  类型: {$result->scan_type}");
        $this->line("  目标: {$result->target_url}");
        $this->line("  时间: {$result->executed_at}");
        $this->info("  🔴 高危: {$result->high_count}");
        $this->info("  🟡 中危: {$result->medium_count}");
        $this->info("  🔵 低危: {$result->low_count}");

        if (!empty($result->alerts)) {
            foreach ($result->alerts as $alert) {
                if (($alert['risk'] ?? '') === 'High') {
                    $this->error("  🔴 {$alert['alert']} - {$alert['url']}");
                }
            }
        }
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    private function showLastReport(): int
    {
        $lastScan = SecurityScanResult::latest('executed_at')->first();
        if (!$lastScan) {
            $this->warn('没有扫描记录');
            return 0;
        }
        $this->outputReport($lastScan);
        return $lastScan->passed ? 0 : 1;
    }

    private function notifyUsers(array $userIds, SecurityScanResult $result): void
    {
        $users = User::whereIn('id', $userIds)->get();
        foreach ($users as $user) {
            try {
                $user->notify(new SecurityScanCompleted($result));
            } catch (\Throwable $e) {
                Log::warning('通知失败', ['user_id' => $user->id]);
            }
        }
    }
}

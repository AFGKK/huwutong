<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/**
 * D-40: 压测报告生成与归档
 *
 * php artisan benchmark:report
 * php artisan benchmark:report --base-url=http://127.0.0.1:8088/api --target-qps=5000
 */
class BenchmarkReportCommand extends Command
{
    protected $signature = 'benchmark:report
        {--base-url= : API 基础地址，默认 BENCH_BASE_URL 或 http://127.0.0.1:8088/api}
        {--health-url= : 健康检查 URL，默认可从 base-url 推导}
        {--requests=5000 : HTTP 并发压测总请求数}
        {--concurrency=100 : HTTP 并发数}
        {--target-qps=5000 : 达标 QPS 目标}
        {--skip-server : 跳过 benchmark:run 服务端测试}
        {--skip-http : 跳过 HTTP 层压测}
        {--try-k6 : 若 PATH 中有 k6 则执行 qps-target.js}';

    protected $description = 'D-40 生成 benchmark-result.json 压测归档报告';

    public function handle(): int
    {
        $baseUrl = rtrim($this->option('base-url')
            ?: config('benchmark.base_url', env('BENCH_BASE_URL', 'http://127.0.0.1:8088/api')), '/');
        $healthUrl = $this->option('health-url') ?: "{$baseUrl}/health/live";
        $targetQps = (int) $this->option('target-qps');
        $requests = (int) $this->option('requests');
        $concurrency = max(1, (int) $this->option('concurrency'));

        // artisan serve 单进程，高并发会导致连接堆积与超时
        if (preg_match('#:8000(/|$)#', $baseUrl) && $concurrency > 5) {
            $this->warn("检测到 artisan serve (:8000)，并发 {$concurrency} → 5，避免压垮单进程服务器");
            $concurrency = 5;
        }

        $this->info('=== D-40 压测报告生成 ===');
        $this->line("  Health URL: {$healthUrl}");
        $this->line("  Target QPS: {$targetQps}");
        $this->newLine();

        $report = [
            'mission' => 'D-40',
            'title' => '互物通 5000 QPS 达标验证报告',
            'timestamp' => now()->toIso8601String(),
            'environment' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'base_url' => $baseUrl,
                'health_url' => $healthUrl,
                'runtime' => config('benchmark.runtime', env('BENCHMARK_RUNTIME', php_sapi_name())),
                'cache_driver' => config('cache.default'),
                'db_connection' => config('database.default'),
            ],
            'target_qps' => $targetQps,
            'tests' => [],
            'summary' => [],
        ];

        if (! $this->option('skip-http')) {
            $this->components->task('HTTP 层压测', function () use (&$report, $healthUrl, $requests, $concurrency, $targetQps) {
                $report['tests']['http_load'] = $this->runHttpLoad($healthUrl, $requests, $concurrency, $targetQps);

                return true;
            });
        }

        if (! $this->option('skip-server')) {
            $this->components->task('服务端 benchmark:run', function () use (&$report) {
                $report['tests']['server_benchmark'] = $this->runServerBenchmark();

                return true;
            });
        }

        if ($this->option('try-k6')) {
            $this->components->task('k6 qps-target', function () use (&$report, $baseUrl, $targetQps) {
                $k6 = $this->runK6IfAvailable($baseUrl, $targetQps);
                if ($k6) {
                    $report['tests']['k6_qps'] = $k6;
                }

                return true;
            });
        }

        $httpQps = $report['tests']['http_load']['achieved_qps'] ?? 0;
        $k6Qps = $report['tests']['k6_qps']['achieved_qps'] ?? null;
        $bestQps = max($httpQps, (float) ($k6Qps ?? 0));
        $p95 = $report['tests']['http_load']['p95_ms'] ?? null;

        $report['summary'] = [
            'achieved_qps' => $bestQps,
            'http_qps' => $httpQps,
            'k6_qps' => $k6Qps,
            'p95_ms' => $p95,
            'target_qps' => $targetQps,
            'passed' => $bestQps >= $targetQps,
            'pass_ratio' => $targetQps > 0 ? round($bestQps / $targetQps * 100, 1) : 0,
            'recommendation' => $bestQps >= $targetQps
                ? '达标，可归档并进入生产容量规划'
                : '未达标，请使用 D-39 Nginx+PHP-FPM 栈（8088）并安装 k6 后重跑 scripts/benchmark-run-full.ps1',
        ];

        $path = base_path('benchmarks/results/benchmark-result.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->table(
            ['指标', '值'],
            [
                ['HTTP QPS', number_format($httpQps, 1)],
                ['k6 QPS', $k6Qps !== null ? number_format($k6Qps, 1) : '未执行'],
                ['最佳 QPS', number_format($bestQps, 1)],
                ['P95 (ms)', $p95 ?? '-'],
                ['目标', $targetQps],
                ['达标', $report['summary']['passed'] ? '✅ 是' : '❌ 否'],
            ]
        );

        $this->info("报告已归档: {$path}");

        return $report['summary']['passed'] ? self::SUCCESS : self::FAILURE;
    }

    protected function runHttpLoad(string $url, int $requests, int $concurrency, int $targetQps): array
    {
        try {
            Http::timeout(5)->get($url);
        } catch (\Throwable $e) {
            return [
                'status' => 'unavailable',
                'error' => $e->getMessage(),
                'achieved_qps' => 0,
                'passed' => false,
            ];
        }

        $start = microtime(true);
        $success = 0;
        $failed = 0;
        $durations = [];

        $batches = (int) ceil($requests / $concurrency);
        for ($b = 0; $b < $batches; $b++) {
            $batchSize = min($concurrency, $requests - ($b * $concurrency));
            $responses = Http::pool(function ($pool) use ($url, $batchSize) {
                for ($i = 0; $i < $batchSize; $i++) {
                    $pool->as("r{$i}")->timeout(3)->connectTimeout(2)->get($url);
                }
            });

            foreach ($responses as $response) {
                if ($response instanceof \Throwable) {
                    $failed++;
                    continue;
                }
                if ($response->successful()) {
                    $success++;
                    $durations[] = $response->transferStats?->getTransferTime() * 1000 ?? 0;
                } else {
                    $failed++;
                }
            }
        }

        $elapsed = max(microtime(true) - $start, 0.001);
        $qps = round($success / $elapsed, 1);

        sort($durations);
        $p95 = count($durations) > 0
            ? $durations[(int) floor(count($durations) * 0.95)] ?? end($durations)
            : null;

        return [
            'status' => 'completed',
            'url' => $url,
            'requests' => $requests,
            'concurrency' => $concurrency,
            'success' => $success,
            'failed' => $failed,
            'duration_sec' => round($elapsed, 3),
            'achieved_qps' => $qps,
            'p95_ms' => $p95 !== null ? round($p95, 2) : null,
            'target_qps' => $targetQps,
            'passed' => $qps >= $targetQps,
        ];
    }

    protected function runServerBenchmark(): array
    {
        $exitCode = Artisan::call('benchmark:run', ['--quick' => true]);
        $output = Artisan::output();
        $reportPath = storage_path('app/benchmarks/report.json');
        $data = file_exists($reportPath)
            ? json_decode(file_get_contents($reportPath), true)
            : [];

        return [
            'exit_code' => $exitCode,
            'total_qps' => $data['summary']['total_qps'] ?? null,
            'results' => $data['results'] ?? [],
            'output_lines' => substr_count($output, "\n"),
        ];
    }

    protected function runK6IfAvailable(string $baseUrl, int $targetQps): ?array
    {
        $k6 = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where k6 2>nul' : 'which k6 2>/dev/null'));
        if ($k6 === '') {
            $this->warn('  k6 未安装，跳过');

            return null;
        }

        $script = base_path('benchmarks/k6/scripts/qps-target.js');
        $out = base_path('benchmarks/results/k6-qps-summary.json');
        $cmd = sprintf(
            'k6 run -e BASE_URL=%s -e TARGET_QPS=%d -e DURATION=30s --summary-export=%s %s 2>&1',
            escapeshellarg($baseUrl),
            $targetQps,
            escapeshellarg($out),
            escapeshellarg($script)
        );

        exec($cmd, $lines, $code);

        if (file_exists($out)) {
            return json_decode(file_get_contents($out), true);
        }

        return ['exit_code' => $code, 'output' => implode("\n", array_slice($lines, -20))];
    }
}

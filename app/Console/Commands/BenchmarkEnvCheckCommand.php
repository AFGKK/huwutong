<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * D-39: 压测环境就绪检查
 *
 * php artisan benchmark:env-check
 */
class BenchmarkEnvCheckCommand extends Command
{
    protected $signature = 'benchmark:env-check
        {--json : 输出 JSON 格式}';

    protected $description = 'D-39 压测环境就绪检查（Nginx+PHP-FPM+Redis，非 artisan serve）';

    public function handle(): int
    {
        $checks = [
            $this->checkRuntime(),
            $this->checkOpcache(),
            $this->checkDatabase(),
            $this->checkRedis(),
            $this->checkCacheDriver(),
        ];

        $passed = collect($checks)->every(fn (array $c) => $c['ok']);
        $score = collect($checks)->where('ok', true)->count();

        $report = [
            'ready' => $passed,
            'score' => "{$score}/" . count($checks),
            'target_qps_baseline' => 1000,
            'runtime' => config('benchmark.runtime', env('BENCHMARK_RUNTIME', php_sapi_name())),
            'checks' => $checks,
            'recommendations' => $this->recommendations($checks),
            'timestamp' => now()->toIso8601String(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $passed ? self::SUCCESS : self::FAILURE;
        }

        $this->info('=== D-39 压测环境检查 ===');
        $this->newLine();

        foreach ($checks as $check) {
            $icon = $check['ok'] ? '✅' : '❌';
            $this->line("  {$icon} {$check['name']}: {$check['message']}");
        }

        $this->newLine();
        $this->line("  就绪: " . ($passed ? '是' : '否') . " ({$report['score']})");
        $this->line('  基线目标: >1000 QPS（HTTP 层，见 scripts/benchmark-smoke.ps1）');
        $this->newLine();

        foreach ($report['recommendations'] as $tip) {
            $this->warn("  {$tip}");
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    protected function checkRuntime(): array
    {
        $runtime = env('BENCHMARK_RUNTIME');
        $sapi = php_sapi_name();

        if ($runtime === 'nginx-php-fpm') {
            return ['name' => 'Runtime', 'ok' => true, 'message' => 'nginx-php-fpm (BENCHMARK_RUNTIME)'];
        }

        if ($sapi === 'cli-server') {
            return [
                'name' => 'Runtime',
                'ok' => false,
                'message' => '检测到 artisan serve (cli-server)，压测请使用 Nginx+PHP-FPM',
            ];
        }

        if ($sapi === 'fpm-fcgi' || $sapi === 'cgi-fcgi') {
            return ['name' => 'Runtime', 'ok' => true, 'message' => "PHP-FPM ({$sapi})"];
        }

        return [
            'name' => 'Runtime',
            'ok' => false,
            'message' => "当前 SAPI: {$sapi}，建议使用 deploy/benchmark 栈",
        ];
    }

    protected function checkOpcache(): array
    {
        if (! function_exists('opcache_get_status')) {
            return ['name' => 'OPcache', 'ok' => false, 'message' => 'OPcache 扩展未安装'];
        }

        $status = @opcache_get_status(false);
        $enabled = is_array($status) && ($status['opcache_enabled'] ?? false);

        return [
            'name' => 'OPcache',
            'ok' => $enabled,
            'message' => $enabled ? '已启用' : '未启用（压测需开启 OPcache）',
        ];
    }

    protected function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');

            return ['name' => 'Database', 'ok' => true, 'message' => config('database.default') . ' 连接正常'];
        } catch (\Throwable $e) {
            return ['name' => 'Database', 'ok' => false, 'message' => $e->getMessage()];
        }
    }

    protected function checkRedis(): array
    {
        try {
            if (config('database.redis.client') && class_exists(Redis::class)) {
                Redis::connection()->ping();
            } else {
                Cache::store('redis')->put('benchmark_ping', 1, 5);
            }

            return ['name' => 'Redis', 'ok' => true, 'message' => '连接正常'];
        } catch (\Throwable $e) {
            return ['name' => 'Redis', 'ok' => false, 'message' => $e->getMessage()];
        }
    }

    protected function checkCacheDriver(): array
    {
        $driver = config('cache.default');
        $ok = in_array($driver, ['redis', 'array'], true);

        return [
            'name' => 'Cache Driver',
            'ok' => $ok,
            'message' => $ok ? "cache={$driver}" : "当前 cache={$driver}，压测建议 redis",
        ];
    }

    protected function recommendations(array $checks): array
    {
        $tips = [];
        $byName = collect($checks)->keyBy('name');

        if (! ($byName->get('Runtime')['ok'] ?? false)) {
            $tips[] = '使用 scripts/benchmark-up.ps1 启动 Nginx+PHP-FPM 栈（端口 8088）';
        }

        if (! ($byName->get('OPcache')['ok'] ?? false)) {
            $tips[] = 'deploy/benchmark/Dockerfile.app 已启用 OPcache，请通过 Docker 栈运行';
        }

        if (! ($byName->get('Redis')['ok'] ?? false)) {
            $tips[] = '确认 Redis 已启动：docker compose -f deploy/benchmark/docker-compose.benchmark.yml up -d redis';
        }

        $tips[] = '基线冒烟: powershell -File scripts/benchmark-smoke.ps1';
        $tips[] = 'k6 负载: k6 run -e BASE_URL=http://127.0.0.1:8088/api -e TOKEN=xxx benchmarks/k6/scripts/load-test.js';

        return $tips;
    }
}

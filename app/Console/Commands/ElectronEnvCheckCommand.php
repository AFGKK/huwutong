<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * D-32: Electron 管理壳环境检查
 *
 * php artisan electron:env-check
 */
class ElectronEnvCheckCommand extends Command
{
    protected $signature = 'electron:env-check
        {--json : 输出 JSON}';

    protected $description = 'D-32 Electron 管理壳就绪检查';

    public function handle(): int
    {
        $electronDir = base_path('desktop/electron');
        $required = [
            $this->checkFile($electronDir.'/package.json', 'package.json'),
            $this->checkFile($electronDir.'/config.js', 'config.js'),
            $this->checkFile($electronDir.'/src/main.js', 'main.js'),
            $this->checkFile($electronDir.'/src/preload.js', 'preload.js'),
            $this->checkFile($electronDir.'/src/updater.js', 'updater.js'),
            $this->checkScript(base_path('scripts/electron-dev.ps1'), 'electron-dev.ps1'),
            $this->checkNode(),
        ];
        $optional = [
            $this->checkAdminUrl(),
        ];
        $checks = array_merge($required, $optional);

        $passed = collect($required)->every(fn (array $c) => $c['ok']);
        $score = collect($checks)->where('ok', true)->count();

        $report = [
            'ready' => $passed,
            'score' => "{$score}/".count($checks),
            'admin_url' => config('app.admin_url', env('HWT_ADMIN_URL', 'http://127.0.0.1:8000/build')),
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $passed ? self::SUCCESS : self::FAILURE;
        }

        $this->info('=== D-32 Electron 环境检查 ===');
        foreach ($checks as $check) {
            $icon = $check['ok'] ? '✅' : '❌';
            $this->line("  {$icon} {$check['name']}: {$check['message']}");
        }
        $this->newLine();
        $this->line('  就绪: '.($passed ? '是' : '否')." ({$report['score']})");
        $this->line('  启动: powershell -File scripts/electron-dev.ps1');

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    private function checkFile(string $path, string $name): array
    {
        return [
            'name' => $name,
            'ok' => file_exists($path),
            'message' => file_exists($path) ? '存在' : '缺失',
        ];
    }

    private function checkScript(string $path, string $name): array
    {
        return $this->checkFile($path, $name);
    }

    private function checkNode(): array
    {
        $node = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node 2>nul' : 'which node 2>/dev/null'));

        return [
            'name' => 'Node.js',
            'ok' => $node !== '',
            'message' => $node !== '' ? '已安装' : '未安装（Electron 需要 Node 18+）',
        ];
    }

    private function checkAdminUrl(): array
    {
        $url = rtrim(config('app.admin_url', env('HWT_ADMIN_URL', 'http://127.0.0.1:8000/build')), '/');
        $health = preg_replace('#/build/?$#', '', $url).'/api/health/live';

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(3)->get($health);

            return [
                'name' => '管理后台',
                'ok' => $response->successful(),
                'message' => $response->successful() ? $url : "不可达: {$health}",
            ];
        } catch (\Throwable $e) {
            return [
                'name' => '管理后台',
                'ok' => false,
                'message' => '不可达（需 php artisan serve）',
            ];
        }
    }
}

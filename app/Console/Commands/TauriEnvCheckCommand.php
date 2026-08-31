<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * D-33: Tauri 轻量版环境检查
 *
 * php artisan tauri:env-check
 */
class TauriEnvCheckCommand extends Command
{
    protected $signature = 'tauri:env-check {--json : 输出 JSON}';

    protected $description = 'D-33 Tauri License 查看器就绪检查';

    public function handle(): int
    {
        $tauriDir = base_path('desktop/tauri');
        $required = [
            $this->checkFile($tauriDir.'/package.json', 'package.json'),
            $this->checkFile($tauriDir.'/src/index.html', 'index.html'),
            $this->checkFile($tauriDir.'/src-tauri/Cargo.toml', 'Cargo.toml'),
            $this->checkFile($tauriDir.'/src-tauri/tauri.conf.json', 'tauri.conf.json'),
            $this->checkFile($tauriDir.'/src-tauri/src/lib.rs', 'lib.rs (commands)'),
            $this->checkFile(base_path('sdk/tauri/Cargo.toml'), 'sdk/tauri'),
            $this->checkScript(base_path('scripts/tauri-dev.ps1'), 'tauri-dev.ps1'),
            $this->checkSdkIntegration(),
        ];
        $optional = [
            $this->checkRust(),
            $this->checkBackend(),
        ];
        $checks = array_merge($required, $optional);
        $passed = collect($required)->every(fn (array $c) => $c['ok']);

        $report = [
            'ready' => $passed,
            'score' => collect($required)->where('ok', true)->count().'/'.count($required),
            'target_bundle_mb' => 30,
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $passed ? self::SUCCESS : self::FAILURE;
        }

        $this->info('=== D-33 Tauri 环境检查 ===');
        foreach ($checks as $check) {
            $icon = $check['ok'] ? '✅' : '❌';
            $this->line("  {$icon} {$check['name']}: {$check['message']}");
        }
        $this->newLine();
        $this->line('  就绪: '.($passed ? '是' : '否')." ({$report['score']})");
        $this->line('  启动: powershell -File scripts/tauri-dev.ps1');

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    private function checkFile(string $path, string $name): array
    {
        return ['name' => $name, 'ok' => file_exists($path), 'message' => file_exists($path) ? '存在' : '缺失'];
    }

    private function checkScript(string $path, string $name): array
    {
        return $this->checkFile($path, $name);
    }

    private function checkRust(): array
    {
        $cargo = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where cargo 2>nul' : 'which cargo 2>/dev/null'));

        return [
            'name' => 'Rust/cargo',
            'ok' => $cargo !== '',
            'message' => $cargo !== '' ? '已安装' : '未安装（https://rustup.rs）',
        ];
    }

    private function checkSdkIntegration(): array
    {
        $lib = file_get_contents(base_path('desktop/tauri/src-tauri/src/lib.rs'));

        return [
            'name' => 'sdk/tauri 集成',
            'ok' => str_contains($lib, 'huwutong_sdk::HwtClient') && str_contains($lib, 'lookup_license'),
            'message' => 'public-lookup + SDK validate',
        ];
    }

    private function checkBackend(): array
    {
        try {
            $r = \Illuminate\Support\Facades\Http::timeout(3)->get('http://127.0.0.1:8000/api/health/live');

            return ['name' => '后端 API', 'ok' => $r->successful(), 'message' => $r->successful() ? '可达' : '不可达'];
        } catch (\Throwable) {
            return ['name' => '后端 API', 'ok' => false, 'message' => '不可达（需 php artisan serve）'];
        }
    }
}

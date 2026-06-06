<?php

namespace App\Console\Commands;

use App\Models\DependencyVulnerability;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScanDependencies extends Command
{
    protected $signature = 'deps:scan
        {--ecosystem= : composer 或 npm，不传则扫描全部}
        {--notify : 发现漏洞时发送通知}';

    protected $description = '扫描第三方依赖安全漏洞（composer audit + npm audit）';

    public function handle(): int
    {
        $ecosystem = $this->option('ecosystem');

        if ($ecosystem && ! in_array($ecosystem, ['composer', 'npm'])) {
            $this->error('不支持的生态: composer 或 npm');
            return Command::FAILURE;
        }

        $foundVulnerabilities = [];

        if (! $ecosystem || $ecosystem === 'composer') {
            $this->info('正在扫描 Composer 依赖...');
            $foundVulnerabilities = array_merge(
                $foundVulnerabilities,
                $this->scanComposer(),
            );
        }

        if (! $ecosystem || $ecosystem === 'npm') {
            $this->info('正在扫描 NPM 依赖...');
            $foundVulnerabilities = array_merge(
                $foundVulnerabilities,
                $this->scanNpm(),
            );
        }

        if (empty($foundVulnerabilities)) {
            $this->info('✅ 未发现新的安全漏洞');
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->warn("发现 {$foundVulnerabilities} 个新漏洞");

        if ($this->option('notify')) {
            $this->notifyVulnerabilities($foundVulnerabilities);
        }

        return Command::SUCCESS;
    }

    protected function scanComposer(): int
    {
        $command = 'cd ' . base_path() . ' && composer audit --format=json 2>&1';
        $output = shell_exec($command);

        if (! $output) {
            $this->warn('composer audit 不可用，请确保已安装');
            return 0;
        }

        $data = json_decode($output, true);
        $count = 0;

        if (isset($data['advisories']) && is_array($data['advisories'])) {
            foreach ($data['advisories'] as $package => $advisories) {
                foreach ($advisories as $advisory) {
                    $affectedVersion = $advisory['affectedVersions'] ?? '*';
                    $patchedVersion = $advisory['patchedVersions'] ?? null;

                    $existing = DependencyVulnerability::where('ecosystem', 'composer')
                        ->where('package_name', $package)
                        ->where('cve', $advisory['cve'] ?? null)
                        ->where('status', 'open')
                        ->first();

                    if ($existing) {
                        continue;
                    }

                    DependencyVulnerability::create([
                        'ecosystem' => 'composer',
                        'package_name' => $package,
                        'installed_version' => $this->getInstalledVersion('composer', $package),
                        'patched_version' => $patchedVersion,
                        'cve' => $advisory['cve'] ?? null,
                        'title' => $advisory['title'] ?? '未知漏洞',
                        'description' => $advisory['link'] ?? '',
                        'severity' => $this->mapSeverity($advisory['severity'] ?? 'medium'),
                        'source' => 'audit',
                        'references' => $advisory['link'] ? [$advisory['link']] : [],
                        'status' => 'open',
                        'detected_at' => now(),
                    ]);

                    $count++;
                    $this->line("  ⚠️  [{$package}] {$advisory['cve']} - {$advisory['title']}");
                }
            }
        }

        return $count;
    }

    protected function scanNpm(): int
    {
        $command = 'cd ' . base_path() . ' && npm audit --json 2>&1';
        $output = shell_exec($command);

        if (! $output) {
            $this->warn('npm audit 执行失败，请确保已安装 npm');
            return 0;
        }

        $data = json_decode($output, true);
        $count = 0;

        if (! $data || isset($data['error'])) {
            // npm audit 可能因网络问题失败，忽略
            return 0;
        }

        $vulnerabilities = $data['vulnerabilities'] ?? [];

        foreach ($vulnerabilities as $package => $info) {
            if (empty($info['via'])) continue;

            $severity = $this->mapNpmSeverity($info['severity'] ?? 'moderate');

            foreach ($info['via'] as $advisory) {
                if (is_string($advisory)) continue;

                $cve = $advisory['cve'] ?? null;

                $existing = DependencyVulnerability::where('ecosystem', 'npm')
                    ->where('package_name', $package)
                    ->where('cve', $cve)
                    ->where('status', 'open')
                    ->first();

                if ($existing) continue;

                DependencyVulnerability::create([
                    'ecosystem' => 'npm',
                    'package_name' => $package,
                    'installed_version' => $info['range'] ?? $info['version'] ?? '*',
                    'patched_version' => $advisory['patchedVersions'] ?? null,
                    'cve' => $cve,
                    'title' => $advisory['title'] ?? '未知漏洞',
                    'description' => $advisory['url'] ?? '',
                    'severity' => $severity,
                    'source' => 'audit',
                    'references' => $advisory['url'] ? [$advisory['url']] : [],
                    'status' => 'open',
                    'detected_at' => now(),
                ]);

                $count++;
                $this->line("  ⚠️  [{$package}] " . ($cve ?? '') . " - {$advisory['title']}");
            }
        }

        return $count;
    }

    protected function getInstalledVersion(string $ecosystem, string $package): string
    {
        if ($ecosystem === 'composer') {
            $installedPath = base_path('vendor/composer/installed.json');
            if (file_exists($installedPath)) {
                $installed = json_decode(file_get_contents($installedPath), true);
                $packages = $installed['packages'] ?? $installed;
                foreach ($packages as $pkg) {
                    if (($pkg['name'] ?? '') === $package) {
                        return $pkg['version'] ?? '*';
                    }
                }
            }
        }
        return '*';
    }

    protected function mapSeverity(?string $severity): string
    {
        return match (strtolower($severity ?? '')) {
            'critical' => 'critical',
            'high' => 'high',
            'medium', 'moderate' => 'medium',
            'low' => 'low',
            default => 'medium',
        };
    }

    protected function mapNpmSeverity(string $severity): string
    {
        return match ($severity) {
            'critical' => 'critical',
            'high' => 'high',
            'moderate', 'medium' => 'medium',
            'low' => 'low',
            default => 'medium',
        };
    }

    protected function notifyVulnerabilities(int $count): void
    {
        try {
            $channels = ['slack', 'dingtalk'];
            $message = "🔒 依赖安全扫描发现 {$count} 个新漏洞，请及时处理";

            foreach ($channels as $channel) {
                $webhookUrl = config("services.{$channel}.webhook_url");
                if ($webhookUrl) {
                    Http::post($webhookUrl, ['text' => $message]);
                }
            }

            Log::warning('依赖漏洞扫描通知', ['count' => $count]);
        } catch (\Exception $e) {
            Log::error('漏洞通知发送失败', ['error' => $e->getMessage()]);
        }
    }
}

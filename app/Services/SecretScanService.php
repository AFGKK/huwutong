<?php

namespace App\Services;

use App\Models\License;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * 密钥泄露扫描服务 (M1.3-29)
 *
 * 核心职责：
 * 1. 仓库代码扫描 — 遍历项目文件，检测硬编码密钥
 * 2. 泄漏告警 — 检测到泄露后通知管理员
 * 3. 自动处置 — 自动吊销/轮换已泄露的密钥
 * 4. 扫描报告 — 生成扫描结果报告
 */
class SecretScanService
{
    protected array $patterns;
    protected array $excludePaths;

    public function __construct()
    {
        $this->patterns = config('secret-scan.patterns', []);
        $this->excludePaths = config('secret-scan.exclude_paths', ['vendor', 'node_modules', 'storage', '.git']);
    }

    /**
     * 执行全量扫描
     *
     * @param string|null $path 指定扫描路径，null 为项目根目录
     * @return array{scanned: int, leaks: array, total_findings: int}
     */
    public function scan(?string $path = null): array
    {
        $path = $path ?? base_path();
        $scanned = 0;
        $leaks = [];

        $files = File::allFiles($path);

        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();

            // 跳过排除目录
            if ($this->isExcluded($relativePath)) {
                continue;
            }

            // 跳过二进制文件
            $extension = $file->getExtension();
            if (in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'zip', 'gz', 'phar'], true)) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            $scanned++;

            foreach ($this->patterns as $pattern => $label) {
                if (preg_match('/' . $pattern . '/', $content, $matches)) {
                    $matched = $matches[0] ?? '';
                    // 截断显示（只显示前20个字符）
                    $truncated = mb_substr($matched, 0, 20) . (mb_strlen($matched) > 20 ? '...' : '');

                    $leaks[] = [
                        'file' => $relativePath,
                        'pattern' => $label,
                        'matched' => $truncated,
                        'severity' => $this->determineSeverity($pattern),
                    ];
                }
            }
        }

        // 记录扫描结果
        Log::channel('secret-scan')->info('密钥泄露扫描完成', [
            'scanned_files' => $scanned,
            'leaks_found' => count($leaks),
        ]);

        return [
            'scanned' => $scanned,
            'leaks' => $leaks,
            'total_findings' => count($leaks),
            'scanned_at' => now()->toIso8601String(),
        ];
    }

    /**
     * 快速扫描（仅扫描最近修改的文件）
     */
    public function quickScan(): array
    {
        $cutoff = Carbon::now()->subHours(6);
        $leaks = [];
        $scanned = 0;

        $files = File::allFiles(base_path());

        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();
            if ($this->isExcluded($relativePath)) {
                continue;
            }

            // 只扫描最近修改的文件
            if ($file->getMTime() < $cutoff->timestamp) {
                continue;
            }

            $extension = $file->getExtension();
            if (in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'zip', 'gz', 'phar'], true)) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) continue;

            $scanned++;

            foreach ($this->patterns as $pattern => $label) {
                if (preg_match('/' . $pattern . '/', $content, $matches)) {
                    $matched = $matches[0] ?? '';
                    $truncated = mb_substr($matched, 0, 20) . (mb_strlen($matched) > 20 ? '...' : '');

                    $leaks[] = [
                        'file' => $relativePath,
                        'pattern' => $label,
                        'matched' => $truncated,
                        'severity' => $this->determineSeverity($pattern),
                    ];
                }
            }
        }

        return [
            'scanned' => $scanned,
            'leaks' => $leaks,
            'total_findings' => count($leaks),
            'scanned_at' => now()->toIso8601String(),
        ];
    }

    /**
     * 处理泄露告警
     */
    public function handleLeak(array $leak): void
    {
        $rateKey = 'secret_alert:' . md5($leak['pattern']);
        $lastAlert = Cache::get($rateKey);

        if ($lastAlert) {
            return; // 限频，避免重复告警
        }

        // 记录日志
        Log::channel('secret-scan')->warning('检测到密钥泄露', [
            'file' => $leak['file'],
            'pattern' => $leak['pattern'],
            'severity' => $leak['severity'],
        ]);

        // 发送通知（简化实现）
        $this->sendAlert($leak);

        // 自动吊销
        if (config('secret-scan.remediation.auto_revoke', true)) {
            $this->autoRevoke($leak);
        }

        // 设置告警限频
        Cache::put($rateKey, true, now()->addMinutes(
            config('secret-scan.alert.rate_limit_minutes', 60)
        ));
    }

    /**
     * 批量处理所有泄露
     */
    public function processLeaks(array $scanResult): array
    {
        $processed = 0;
        foreach ($scanResult['leaks'] as $leak) {
            $this->handleLeak($leak);
            $processed++;
        }
        return ['processed' => $processed, 'total' => $scanResult['total_findings']];
    }

    /**
     * 自动吊销已泄露的密钥
     */
    protected function autoRevoke(array $leak): void
    {
        $matched = $leak['matched'] ?? '';

        // 检测到 License Key 泄露 → 自动吊销
        if (str_contains($leak['pattern'], 'License Key')) {
            $license = License::where('license_key', 'like', substr($matched, 0, 20) . '%')->first();
            if ($license && $license->status !== 'revoked') {
                $license->update(['status' => 'revoked']);
                Log::channel('secret-scan')->info('自动吊销泄露的 License', [
                    'license_key' => $license->license_key,
                ]);
            }
        }
    }

    /**
     * 发送告警通知
     */
    protected function sendAlert(array $leak): void
    {
        $roles = config('secret-scan.alert.notify_roles', ['super-admin', 'admin']);
        $admins = User::whereHas('roles', fn($q) => $q->whereIn('name', $roles))->get();

        // 简化实现：记录日志
        Log::channel('secret-scan')->warning('密钥泄露告警已触发', [
            'file' => $leak['file'],
            'pattern' => $leak['pattern'],
            'notify_count' => $admins->count(),
        ]);
    }

    /**
     * 获取扫描统计
     */
    public function getStats(): array
    {
        return [
            'patterns_count' => count($this->patterns),
            'exclude_paths' => $this->excludePaths,
            'mode' => config('secret-scan.mode', 'full'),
            'auto_revoke' => config('secret-scan.remediation.auto_revoke', true),
            'schedule_full' => config('secret-scan.schedule.full_scan', '0 2 * * 0'),
        ];
    }

    /**
     * 判断严重级别
     */
    protected function determineSeverity(string $pattern): string
    {
        $critical = ['sk_live_', '-----BEGIN RSA PRIVATE KEY', '-----BEGIN OPENSSH PRIVATE KEY'];
        $high = ['AKIA', 'ghp_', 'HWT-'];

        foreach ($critical as $c) {
            if (str_contains($pattern, $c)) return 'critical';
        }
        foreach ($high as $h) {
            if (str_contains($pattern, $h)) return 'high';
        }
        return 'medium';
    }

    protected function isExcluded(string $path): bool
    {
        foreach ($this->excludePaths as $excluded) {
            if (str_starts_with($path, $excluded) || str_contains($path, '/' . $excluded . '/')) {
                return true;
            }
        }
        return false;
    }
}

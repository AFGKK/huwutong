<?php

namespace App\Services;

use App\Models\SdkDestroyCommand;
use App\Models\SdkIntegrityCheck;
use App\Models\SdkVersion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * M2-17 SDK完整性自检 + 远程自毁
 *
 * 提供SDK文件完整性校验、篡改检测、远程销毁命令下发与执行确认。
 * 依赖 M2-16 SDK版本兼容策略。
 */
class SdkIntegrityService
{
    private const CACHE_KEY_PENDING_DESTROY = 'sdk:pending_destroy:%s';
    private const CACHE_TTL = 300; // 5分钟

    /**
     * SDK提交完整性检查结果
     */
    public function submitCheck(array $data): SdkIntegrityCheck
    {
        $check = SdkIntegrityCheck::create([
            'sdk_instance_id' => $data['sdk_instance_id'],
            'language' => $data['language'],
            'sdk_version' => $data['sdk_version'],
            'machine_id' => $data['machine_id'] ?? null,
            'passed' => $data['passed'] ?? false,
            'file_checksums' => $data['file_checksums'] ?? null,
            'failed_files' => $data['failed_files'] ?? null,
            'error_message' => $data['error_message'] ?? null,
            'client_ip' => request()->ip(),
            'checked_at' => now(),
        ]);

        // 如果校验失败且超过阈值，自动创建销毁命令
        if (!$check->passed && config('sdk-integrity.integrity.alert_on_failure', true)) {
            $this->autoHandleFailure($check);
        }

        return $check;
    }

    /**
     * 自动处理校验失败
     */
    private function autoHandleFailure(SdkIntegrityCheck $check): void
    {
        $threshold = config('sdk-integrity.integrity.failure_threshold', 3);

        // 统计该实例最近连续失败次数
        $recentFails = SdkIntegrityCheck::byInstance($check->sdk_instance_id)
            ->failed()
            ->where('checked_at', '>=', now()->subDay())
            ->count();

        if ($recentFails >= $threshold) {
            $this->issueDestroyCommand(
                sdkInstanceId: $check->sdk_instance_id,
                triggerType: 'integrity_failure',
                reason: "SDK实例 {$check->sdk_instance_id} 完整性校验连续失败 {$recentFails} 次（阈值: {$threshold}）",
                destroyMode: config('sdk-integrity.self_destruct.default_mode', 'soft'),
            );

            // 集成 AlertEngineService 发送告警
            try {
                $alertService = app(AlertEngineService::class);
                $alertService->fireManual(
                    'sdk_integrity_failure',
                    "SDK完整性校验连续失败: {$check->sdk_instance_id} - {$recentFails}次失败",
                    'high',
                    [
                        'sdk_instance_id' => $check->sdk_instance_id,
                        'language' => $check->language,
                        'sdk_version' => $check->sdk_version,
                        'failed_files' => $check->failed_files,
                        'recent_fails' => $recentFails,
                        'threshold' => $threshold,
                        'action_taken' => 'destroy_command_issued',
                    ]
                );
                Log::info('SdkIntegrity: alert sent', ['sdk_instance_id' => $check->sdk_instance_id]);
            } catch (\Throwable $e) {
                Log::warning('SdkIntegrity: failed to send alert', [
                    'sdk_instance_id' => $check->sdk_instance_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 下发远程销毁命令
     */
    public function issueDestroyCommand(
        ?string $sdkInstanceId = null,
        ?string $language = null,
        ?string $versionConstraint = null,
        string $triggerType = 'remote_command',
        ?string $reason = null,
        string $destroyMode = 'soft',
        ?int $createdBy = null,
    ): SdkDestroyCommand {
        $commandId = Str::uuid()->toString();
        $ttl = config('sdk-integrity.self_destruct.command_ttl', 2592000);

        $command = SdkDestroyCommand::create([
            'command_id' => $commandId,
            'sdk_instance_id' => $sdkInstanceId,
            'language' => $language,
            'version_constraint' => $versionConstraint,
            'destroy_mode' => $destroyMode,
            'trigger_type' => $triggerType,
            'reason' => $reason,
            'status' => 'pending',
            'dispatched_instances' => [],
            'confirmed_instances' => [],
            'affected_count' => 0,
            'expires_at' => now()->addSeconds($ttl),
            'created_by' => $createdBy,
        ]);

        // 清除缓存，让SDK下次轮询可获取
        if ($sdkInstanceId) {
            Cache::forget(sprintf(self::CACHE_KEY_PENDING_DESTROY, $sdkInstanceId));
        }

        // 发送销毁命令告警
        try {
            $alertService = app(AlertEngineService::class);
            $alertService->fireManual(
                'sdk_destroy_command_issued',
                "SDK远程销毁命令已下发: {$triggerType} - {$reason}",
                $destroyMode === 'hard' ? 'critical' : 'high',
                [
                    'command_id' => $command->command_id,
                    'sdk_instance_id' => $sdkInstanceId,
                    'trigger_type' => $triggerType,
                    'destroy_mode' => $destroyMode,
                    'language' => $language,
                    'reason' => $reason,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('SdkIntegrity: failed to send destroy alert', [
                'command_id' => $command->command_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $command;
    }

    /**
     * SDK轮询是否有待处理的销毁命令
     */
    public function pollDestroyCommand(string $sdkInstanceId): ?array
    {
        // 先查缓存
        $cached = Cache::get(sprintf(self::CACHE_KEY_PENDING_DESTROY, $sdkInstanceId));
        if ($cached !== null) {
            return $cached;
        }

        $command = SdkDestroyCommand::byInstance($sdkInstanceId)
            ->active()
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->first();

        if (!$command) {
            Cache::put(sprintf(self::CACHE_KEY_PENDING_DESTROY, $sdkInstanceId), null, self::CACHE_TTL);
            return null;
        }

        $result = [
            'command_id' => $command->command_id,
            'destroy_mode' => $command->destroy_mode,
            'trigger_type' => $command->trigger_type,
            'reason' => $command->reason,
            'expires_at' => $command->expires_at?->toIso8601String(),
        ];

        // 标记为已下发
        if ($command->status === 'pending') {
            $dispatched = $command->dispatched_instances ?? [];
            if (!in_array($sdkInstanceId, $dispatched)) {
                $dispatched[] = $sdkInstanceId;
            }
            $command->update([
                'status' => 'dispatched',
                'dispatched_instances' => $dispatched,
                'dispatched_at' => now(),
            ]);
        }

        Cache::put(sprintf(self::CACHE_KEY_PENDING_DESTROY, $sdkInstanceId), $result, self::CACHE_TTL);
        return $result;
    }

    /**
     * SDK确认销毁命令已执行
     */
    public function confirmDestroy(string $commandId, string $sdkInstanceId): bool
    {
        $command = SdkDestroyCommand::where('command_id', $commandId)->first();
        if (!$command) {
            return false;
        }

        $confirmed = $command->confirmed_instances ?? [];
        if (!in_array($sdkInstanceId, $confirmed)) {
            $confirmed[] = $sdkInstanceId;
        }

        $allConfirmed = count($confirmed) >= ($command->affected_count ?: 1);
        $command->update([
            'status' => $allConfirmed ? 'confirmed' : 'dispatched',
            'confirmed_instances' => $confirmed,
            'confirmed_at' => $allConfirmed ? now() : $command->confirmed_at,
        ]);

        Cache::forget(sprintf(self::CACHE_KEY_PENDING_DESTROY, $sdkInstanceId));
        return true;
    }

    /**
     * SDK心跳（含完整性状态）
     */
    public function heartbeat(array $data): array
    {
        // 记录最近心跳
        $instanceId = $data['sdk_instance_id'] ?? 'unknown';

        // 检查是否有待处理的销毁命令
        $pendingDestroy = $this->pollDestroyCommand($instanceId);

        // 检查是否需要升级（对接 M2-16）
        $upgradeCheck = null;
        if (!empty($data['language']) && !empty($data['sdk_version'])) {
            $sdkVersionManager = app(SdkVersionManagerService::class);
            $upgradeCheck = $sdkVersionManager->checkUpgrade($data['language'], $data['sdk_version']);
        }

        return [
            'acknowledged' => true,
            'server_time' => now()->toIso8601String(),
            'pending_destroy' => $pendingDestroy,
            'upgrade_check' => $upgradeCheck,
            'check_interval' => config('sdk-integrity.integrity.check_interval', 86400),
        ];
    }

    /**
     * 获取仪表盘数据
     */
    public function getDashboard(): array
    {
        $totalChecks = SdkIntegrityCheck::count();
        $failedChecks = SdkIntegrityCheck::failed()->count();
        $passRate = $totalChecks > 0 ? round((($totalChecks - $failedChecks) / $totalChecks) * 100, 1) : 100;

        $activeCommands = SdkDestroyCommand::active()->count();
        $totalCommands = SdkDestroyCommand::count();
        $confirmedCommands = SdkDestroyCommand::where('status', 'confirmed')->count();

        // 最近24小时检查趋势
        $last24h = SdkIntegrityCheck::where('checked_at', '>=', now()->subDay())->count();
        $last24hFailed = SdkIntegrityCheck::failed()->where('checked_at', '>=', now()->subDay())->count();

        // 按语言统计
        $byLanguage = SdkIntegrityCheck::selectRaw('language, COUNT(*) as total, SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed')
            ->groupBy('language')
            ->get()
            ->keyBy('language');

        // 按触发类型统计销毁命令
        $byTrigger = SdkDestroyCommand::selectRaw('trigger_type, COUNT(*) as total')
            ->groupBy('trigger_type')
            ->get()
            ->keyBy('trigger_type');

        return [
            'total_checks' => $totalChecks,
            'failed_checks' => $failedChecks,
            'pass_rate' => $passRate,
            'active_commands' => $activeCommands,
            'total_commands' => $totalCommands,
            'confirmed_commands' => $confirmedCommands,
            'last_24h' => ['total' => $last24h, 'failed' => $last24hFailed],
            'by_language' => $byLanguage,
            'by_trigger_type' => $byTrigger,
            'unique_instances' => SdkIntegrityCheck::distinct('sdk_instance_id')->count('sdk_instance_id'),
        ];
    }

    /**
     * 获取完整性检查记录列表
     */
    public function getChecks(array $filters = [], int $perPage = 20): array
    {
        $query = SdkIntegrityCheck::query();

        if (!empty($filters['sdk_instance_id'])) {
            $query->byInstance($filters['sdk_instance_id']);
        }
        if (!empty($filters['language'])) {
            $query->where('language', $filters['language']);
        }
        if (!empty($filters['passed'])) {
            $query->where('passed', $filters['passed'] === 'true' || $filters['passed'] === true);
        }
        if (!empty($filters['date_from'])) {
            $query->where('checked_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('checked_at', '<=', $filters['date_to']);
        }

        $query->recent();

        return $query->paginate(min($perPage, 100))->toArray();
    }

    /**
     * 获取销毁命令列表
     */
    public function getCommands(array $filters = [], int $perPage = 20): array
    {
        $query = SdkDestroyCommand::query()->with('creator:id,name');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['trigger_type'])) {
            $query->where('trigger_type', $filters['trigger_type']);
        }
        if (!empty($filters['language'])) {
            $query->where('language', $filters['language']);
        }
        if (!empty($filters['sdk_instance_id'])) {
            $query->byInstance($filters['sdk_instance_id']);
        }

        $query->orderByDesc('id');

        return $query->paginate(min($perPage, 100))->toArray();
    }

    /**
     * 取消销毁命令
     */
    public function cancelCommand(int $id): ?SdkDestroyCommand
    {
        $command = SdkDestroyCommand::findOrFail($id);
        if ($command->status === 'confirmed') {
            return null; // 已确认执行的不能取消
        }

        $command->update(['status' => 'cancelled']);

        // 清除相关缓存
        if ($command->sdk_instance_id) {
            Cache::forget(sprintf(self::CACHE_KEY_PENDING_DESTROY, $command->sdk_instance_id));
        }

        return $command;
    }

    /**
     * 处理过期销毁命令
     */
    public function processExpiredCommands(): int
    {
        $expired = SdkDestroyCommand::active()
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($expired as $command) {
            $command->update(['status' => 'expired']);
            if ($command->sdk_instance_id) {
                Cache::forget(sprintf(self::CACHE_KEY_PENDING_DESTROY, $command->sdk_instance_id));
            }
            $count++;
        }

        return $count;
    }

    /**
     * 批量销毁（按条件筛选SDK实例）
     */
    public function batchDestroy(array $criteria, string $reason, string $mode = 'soft', ?int $userId = null): array
    {
        $results = [];
        $query = SdkIntegrityCheck::query();

        if (!empty($criteria['language'])) {
            $query->where('language', $criteria['language']);
        }
        if (!empty($criteria['sdk_version'])) {
            $query->where('sdk_version', $criteria['sdk_version']);
        }
        if (!empty($criteria['failed_only'])) {
            $query->failed();
        }
        if (!empty($criteria['date_before'])) {
            $query->where('checked_at', '<=', $criteria['date_before']);
        }

        $instances = $query->distinct('sdk_instance_id')
            ->limit(config('sdk-integrity.self_destruct.batch_max', 100))
            ->pluck('sdk_instance_id');

        foreach ($instances as $instanceId) {
            $command = $this->issueDestroyCommand(
                sdkInstanceId: $instanceId,
                triggerType: 'remote_command',
                reason: $reason,
                destroyMode: $mode,
                createdBy: $userId,
            );
            $results[] = $command->command_id;
        }

        return $results;
    }

    /**
     * 获取SDK受保护文件清单
     */
    public function getProtectedFiles(?string $language = null): array
    {
        $files = config('sdk-integrity.protected_files', []);

        if ($language) {
            return $files[$language] ?? [];
        }

        return $files;
    }

    /**
     * 获取SDK完整性配置（SDK客户端启动时拉取）
     */
    public function getSdkConfig(): array
    {
        return [
            'integrity_enabled' => config('sdk-integrity.integrity.enabled', true),
            'algorithm' => config('sdk-integrity.integrity.algorithm', 'sha256'),
            'check_interval' => config('sdk-integrity.integrity.check_interval', 86400),
            'grace_period' => config('sdk-integrity.integrity.grace_period', 3600),
            'failure_threshold' => config('sdk-integrity.integrity.failure_threshold', 3),
            'protected_files' => $this->getProtectedFiles(),
            'endpoints' => config('sdk-integrity.reporting', []),
            'self_destruct_enabled' => config('sdk-integrity.self_destruct.enabled', true),
            'default_destroy_mode' => config('sdk-integrity.self_destruct.default_mode', 'soft'),
        ];
    }
}

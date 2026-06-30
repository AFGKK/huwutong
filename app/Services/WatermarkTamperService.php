<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseVerificationLog;
use App\Models\LicenseWatermark;
use App\Models\TamperEvent;
use App\Models\TamperProtectionConfig;
use App\Models\WatermarkTraceAudit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * License 水印与防篡改服务
 *
 * 提供：
 * - 数字水印嵌入与提取（用于溯源泄密者）
 * - License 完整性哈希生成与验证
 * - 防篡改事件检测与处理
 * - 验证日志记录与分析
 * - SDK 验证支持
 */
class WatermarkTamperService
{
    const WATERMARK_ALGORITHM_STEALTH = 'stealth';
    const WATERMARK_ALGORITHM_HMAC = 'hmac';
    const WATERMARK_ALGORITHM_BLOOM = 'bloom';

    // ─── 水印管理 ───

    /**
     * 生成水印标识
     */
    public function generateWatermarkKey(): string
    {
        return 'wm_' . Str::random(40);
    }

    /**
     * 为 License 嵌入水印
     */
    public function embedWatermark(License $license, array $sourceInfo = []): LicenseWatermark
    {
        $watermarkKey = $this->generateWatermarkKey();

        // 构建水印数据（溯源信息）
        $watermarkData = array_merge([
            'embedded_at' => now()->toIso8601String(),
            'license_key' => $license->license_key,
            'customer_id' => $license->customer_id,
            'tenant_id' => $license->tenant_id,
            'watermark_id' => $watermarkKey,
        ], $sourceInfo);

        // 计算完整性哈希
        $integrityHash = $this->computeIntegrityHash($license, $watermarkKey);

        DB::transaction(function () use ($license, $watermarkKey, $watermarkData, $integrityHash) {
            // 写入水印记录
            LicenseWatermark::create([
                'license_id' => $license->id,
                'watermark_key' => $watermarkKey,
                'algorithm' => self::WATERMARK_ALGORITHM_STEALTH,
                'watermark_data' => $watermarkData,
                'embed_location' => 'metadata',
                'status' => 'active',
            ]);

            // 更新 License 水印字段
            $license->update([
                'watermark_key' => $watermarkKey,
                'integrity_hash' => $integrityHash,
                'metadata' => array_merge($license->metadata ?? [], [
                    '_wm' => substr($watermarkKey, 0, 16), // 仅存水印前缀作为隐式标记
                    '_ih' => substr($integrityHash, 0, 16),
                ]),
            ]);
        });

        return LicenseWatermark::where('watermark_key', $watermarkKey)->first();
    }

    /**
     * 提取 License 的水印信息
     */
    public function extractWatermark(License $license): ?LicenseWatermark
    {
        if (!$license->watermark_key) {
            return null;
        }

        return LicenseWatermark::where('watermark_key', $license->watermark_key)
            ->where('status', 'active')
            ->first();
    }

    /**
     * 吊销水印
     */
    public function revokeWatermark(LicenseWatermark $watermark): void
    {
        $watermark->update([
            'status' => 'revoked',
        ]);

        Log::warning('License 水印已吊销', [
            'license_id' => $watermark->license_id,
            'watermark_key' => $watermark->watermark_key,
        ]);
    }

    /**
     * 根据水印追踪泄密来源
     */
    public function traceByWatermark(string $watermarkKey): ?array
    {
        $watermark = LicenseWatermark::with('license.customer')
            ->where('watermark_key', $watermarkKey)
            ->first();

        if (!$watermark) {
            return null;
        }

        return [
            'watermark' => $watermark,
            'license' => $watermark->license,
            'customer' => $watermark->license?->customer,
            'source_info' => $watermark->watermark_data,
            'embed_time' => $watermark->created_at,
        ];
    }

    /**
     * 搜索水印（支持前缀模糊匹配）
     */
    public function searchWatermarks(string $keyword, int $limit = 20): array
    {
        return LicenseWatermark::with('license.customer')
            ->where('watermark_key', 'like', "%{$keyword}%")
            ->orWhereHas('license', function ($q) use ($keyword) {
                $q->where('license_key', 'like', "%{$keyword}%");
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    // ─── 完整性哈希 ───

    /**
     * 计算 License 的完整性哈希
     */
    public function computeIntegrityHash(License $license, ?string $watermarkKey = null): string
    {
        $data = implode('|', [
            $license->license_key,
            $license->type,
            $license->status,
            $license->seats,
            $license->max_devices,
            $license->expires_at?->toIso8601String() ?? 'never',
            $watermarkKey ?? $license->watermark_key ?? '',
            config('app.key'),
        ]);

        return hash('sha3-256', $data);
    }

    /**
     * 验证 License 完整性
     */
    public function verifyIntegrity(License $license): array
    {
        $expectedHash = $this->computeIntegrityHash($license);

        if (!$license->integrity_hash) {
            return [
                'passed' => false,
                'reason' => 'no_hash',
                'message' => 'License 尚未设置完整性哈希',
            ];
        }

        if (!hash_equals($expectedHash, $license->integrity_hash)) {
            return [
                'passed' => false,
                'reason' => 'hash_mismatch',
                'message' => '完整性哈希不匹配，License 数据可能已被篡改',
            ];
        }

        return [
            'passed' => true,
            'reason' => 'ok',
            'message' => '完整性验证通过',
        ];
    }

    /**
     * 重新计算并更新完整性哈希
     */
    public function refreshIntegrityHash(License $license): string
    {
        $hash = $this->computeIntegrityHash($license);
        $license->update(['integrity_hash' => $hash]);
        return $hash;
    }

    // ─── 验证日志 ───

    /**
     * 记录验证日志
     */
    public function logVerification(
        License $license,
        string $result,
        array $context = []
    ): LicenseVerificationLog {
        return LicenseVerificationLog::create([
            'license_id' => $license->id,
            'license_key' => $license->license_key,
            'verifier_ip' => $context['ip'] ?? request()->ip(),
            'verifier_fingerprint' => $context['fingerprint'] ?? null,
            'result' => $result,
            'detail' => $context['detail'] ?? null,
            'signature_algorithm' => $context['algorithm'] ?? null,
            'verification_data' => $context['data'] ?? null,
            'is_sdk_verified' => $context['is_sdk'] ?? false,
            'sdk_version' => $context['sdk_version'] ?? null,
        ]);
    }

    /**
     * 获取 License 的验证历史
     */
    public function getVerificationHistory(License $license, int $limit = 50): array
    {
        return LicenseVerificationLog::where('license_id', $license->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * 获取验证统计
     */
    public function getVerificationStats(int $days = 30): array
    {
        $since = now()->subDays($days);

        return [
            'total' => LicenseVerificationLog::where('created_at', '>=', $since)->count(),
            'pass' => LicenseVerificationLog::where('created_at', '>=', $since)->where('result', 'pass')->count(),
            'fail' => LicenseVerificationLog::where('created_at', '>=', $since)->where('result', 'fail')->count(),
            'tamper' => LicenseVerificationLog::where('created_at', '>=', $since)->where('result', 'tamper')->count(),
            'by_sdk' => LicenseVerificationLog::where('created_at', '>=', $since)->where('is_sdk_verified', true)->count(),
            'by_day' => LicenseVerificationLog::selectRaw('DATE(created_at) as date, result, count(*) as count')
                ->where('created_at', '>=', $since)
                ->groupBy('date', 'result')
                ->orderBy('date')
                ->get()
                ->toArray(),
        ];
    }

    // ─── 防篡改事件 ───

    /**
     * 记录防篡改事件
     */
    public function recordTamperEvent(array $data): TamperEvent
    {
        $event = TamperEvent::create([
            'license_id' => $data['license_id'] ?? null,
            'license_key' => $data['license_key'] ?? null,
            'event_type' => $data['event_type'],
            'severity' => $data['severity'] ?? 'medium',
            'event_data' => $data['event_data'] ?? null,
            'source_ip' => $data['ip'] ?? request()->ip(),
            'source_fingerprint' => $data['fingerprint'] ?? null,
        ]);

        // 检查是否需要自动触发防篡改策略
        $this->evaluateTamperPolicies($event);

        Log::warning('防篡改事件已记录', [
            'event_id' => $event->id,
            'event_type' => $event->event_type,
            'license_key' => $event->license_key ?? 'N/A',
            'severity' => $event->severity,
        ]);

        return $event;
    }

    /**
     * 评估防篡改策略
     */
    protected function evaluateTamperPolicies(TamperEvent $event): void
    {
        $policies = TamperProtectionConfig::where('is_active', true)
            ->where('rule_type', $event->event_type)
            ->get();

        foreach ($policies as $policy) {
            $cacheKey = "tamper_policy:{$policy->id}:{$event->license_key}";

            // 检查冷却时间
            $recentCount = Cache::get($cacheKey, 0);

            if ($recentCount >= $policy->threshold) {
                // 触发策略动作
                $this->executePolicyAction($policy, $event);
                continue;
            }

            Cache::put($cacheKey, $recentCount + 1, now()->addSeconds($policy->cooldown_seconds));
        }
    }

    /**
     * 执行防篡改策略动作
     */
    protected function executePolicyAction(TamperProtectionConfig $policy, TamperEvent $event): void
    {
        $actions = $policy->actions ?? [];

        foreach ($actions as $action) {
            match ($action['type'] ?? '') {
                'alert' => $this->triggerAlert($policy, $event),
                'suspend_license' => $this->suspendLicense($event),
                'notify_admin' => $this->notifyAdmin($policy, $event),
                default => null,
            };
        }
    }

    /**
     * 触发告警
     */
    protected function triggerAlert(TamperProtectionConfig $policy, TamperEvent $event): void
    {
        try {
            $alertService = app(AlertEngineService::class);
            $alertService->fireManual(
                'tamper_detected',
                "防篡改策略触发: {$policy->rule_name} - {$event->event_type}",
                $event->severity,
                [
                    'policy_id' => $policy->id,
                    'event_id' => $event->id,
                    'license_key' => $event->license_key,
                ]
            );
        } catch (\Exception $e) {
            Log::warning('触发防篡改告警失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 暂停 License
     */
    protected function suspendLicense(TamperEvent $event): void
    {
        if (!$event->license_id) return;

        try {
            $license = License::find($event->license_id);
            if ($license && $license->status === 'active') {
                $license->update(['status' => 'suspended']);
                Log::critical('防篡改策略自动暂停 License', [
                    'license_id' => $license->id,
                    'license_key' => $license->license_key,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('暂停 License 失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 通知管理员
     */
    protected function notifyAdmin(TamperProtectionConfig $policy, TamperEvent $event): void
    {
        try {
            $notificationService = app(NotificationService::class);
            $notificationService->notifyAdmins(
                "防篡改告警: {$policy->rule_name}",
                "事件类型: {$event->event_type}\nLicense: {$event->license_key}\n严重级别: {$event->severity}"
            );
        } catch (\Exception $e) {
            Log::warning('防篡改通知失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 解决防篡改事件
     */
    public function resolveTamperEvent(TamperEvent $event, string $resolution): void
    {
        $event->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolution' => $resolution,
            'resolved_by' => auth()->id(),
        ]);
    }

    /**
     * 获取防篡改事件列表
     */
    public function getTamperEvents(array $filters = [], int $limit = 50): array
    {
        $query = TamperEvent::with(['license', 'resolver']);

        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }
        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (!empty($filters['license_key'])) {
            $query->where('license_key', $filters['license_key']);
        }
        if (!empty($filters['is_resolved'])) {
            $query->where('is_resolved', $filters['is_resolved'] === 'true' || $filters['is_resolved'] === true);
        }

        return $query->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    // ─── 防篡改策略管理 ───

    /**
     * 获取默认策略
     */
    public function getDefaultPolicies(): array
    {
        return [
            [
                'rule_name' => '连续签名验证失败',
                'rule_type' => 'signature',
                'conditions' => ['type' => 'consecutive_failures', 'count' => 3],
                'actions' => [['type' => 'alert', 'channel' => 'database']],
                'severity' => 'high',
                'cooldown_seconds' => 600,
                'threshold' => 3,
                'description' => '同一 License 连续 3 次签名验证失败时触发告警',
            ],
            [
                'rule_name' => '水印不匹配告警',
                'rule_type' => 'watermark',
                'conditions' => ['type' => 'mismatch'],
                'actions' => [['type' => 'alert', 'channel' => 'database'], ['type' => 'notify_admin']],
                'severity' => 'critical',
                'cooldown_seconds' => 300,
                'threshold' => 1,
                'description' => '水印验证不匹配时触发告警并通知管理员',
            ],
            [
                'rule_name' => '时间回滚检测',
                'rule_type' => 'signature',
                'conditions' => ['type' => 'time_rollback'],
                'actions' => [['type' => 'alert', 'channel' => 'database'], ['type' => 'suspend_license']],
                'severity' => 'critical',
                'cooldown_seconds' => 600,
                'threshold' => 2,
                'description' => '检测到系统时间回滚时自动暂停 License',
            ],
            [
                'rule_name' => '异常设备激活模式',
                'rule_type' => 'device',
                'conditions' => ['type' => 'burst_activation'],
                'actions' => [['type' => 'alert', 'channel' => 'database'], ['type' => 'notify_admin']],
                'severity' => 'high',
                'cooldown_seconds' => 3600,
                'threshold' => 10,
                'description' => '同一 License 短时间内大量新设备激活',
            ],
        ];
    }

    /**
     * 初始化默认防篡改策略
     */
    public function seedDefaultPolicies(): void
    {
        foreach ($this->getDefaultPolicies() as $policy) {
            TamperProtectionConfig::firstOrCreate(
                ['rule_name' => $policy['rule_name']],
                $policy
            );
        }
    }

    /**
     * 获取防篡改仪表盘数据
     */
    public function getDashboardData(): array
    {
        $this->seedDefaultPolicies();

        $totalEvents = TamperEvent::count();
        $unresolvedEvents = TamperEvent::where('is_resolved', false)->count();
        $todayEvents = TamperEvent::whereDate('created_at', today())->count();

        $eventsByType = TamperEvent::selectRaw('event_type, count(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type')
            ->toArray();

        $eventsBySeverity = TamperEvent::selectRaw('severity, count(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $recentEvents = TamperEvent::with(['license', 'resolver'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->all();

        $activeWatermarks = LicenseWatermark::where('status', 'active')->count();
        $totalWatermarks = LicenseWatermark::count();

        $verificationStats = $this->getVerificationStats();

        $policies = TamperProtectionConfig::orderBy('rule_type')->get()->all();

        return [
            'total_events' => $totalEvents,
            'unresolved_events' => $unresolvedEvents,
            'today_events' => $todayEvents,
            'events_by_type' => $eventsByType,
            'events_by_severity' => $eventsBySeverity,
            'recent_events' => $recentEvents,
            'active_watermarks' => $activeWatermarks,
            'total_watermarks' => $totalWatermarks,
            'verification_stats' => $verificationStats,
            'policies' => $policies,
        ];
    }

    // ═══════════ 增强暗水印 (M3-10) ═══════════

    /**
     * 嵌入隐写式暗水印（带加密载荷）
     */
    public function embedForensicWatermark(License $license, array $sourceInfo = []): LicenseWatermark
    {
        $watermarkKey = $this->generateWatermarkKey();
        $fingerprint = $sourceInfo['fingerprint'] ?? 'test_fingerprint';
        $ipAddr = $sourceInfo['ip_address'] ?? '0.0.0.0';

        $forensicData = array_merge([
            'embedded_at' => now()->toIso8601String(),
            'license_key_hash' => hash('sha256', $license->license_key),
            'customer_id' => $license->customer_id,
            'customer_name_hash' => $license->customer && $license->customer->name ? hash('sha256', $license->customer->name) : null,
            'tenant_id' => $license->tenant_id,
            'device_fingerprint' => hash('sha256', $fingerprint),
            'ip_address' => $ipAddr,
            'geoip' => $sourceInfo['geoip'] ?? null,
            'watermark_id' => $watermarkKey,
            'nonce' => Str::random(16),
        ], $sourceInfo['extra'] ?? []);

        // 签名载荷
        $forensicData['signature'] = hash_hmac('sha256',
            json_encode($forensicData),
            config('app.key')
        );

        $integrityHash = $this->computeIntegrityHash($license, $watermarkKey);

        DB::transaction(function () use ($license, $watermarkKey, $forensicData, $integrityHash, $sourceInfo) {
            LicenseWatermark::create([
                'license_id' => $license->id,
                'watermark_key' => $watermarkKey,
                'algorithm' => 'forensic_stealth',
                'watermark_data' => $forensicData,
                'forensic_data' => $forensicData,
                'embed_location' => $sourceInfo['embed_location'] ?? 'metadata',
                'embed_type' => $sourceInfo['embed_type'] ?? 'integrity_hash',
                'status' => 'active',
            ]);

            $license->update([
                'watermark_key' => $watermarkKey,
                'integrity_hash' => $integrityHash,
                'metadata' => array_merge($license->metadata ?? [], [
                    '_fwm' => substr($watermarkKey, 0, 8),
                    '_fih' => substr($integrityHash, 0, 12),
                ]),
            ]);
        });

        return LicenseWatermark::where('watermark_key', $watermarkKey)->first();
    }

    /**
     * 提取并验证暗水印
     */
    public function extractAndVerify(License $license): array
    {
        $watermark = $this->extractWatermark($license);
        if (!$watermark) {
            return ['found' => false, 'message' => '未找到水印'];
        }

        // 更新提取次数
        $watermark->increment('extraction_attempts');
        $watermark->update([
            'last_extracted_at' => now(),
            'extracted_by' => auth()->user()?->email ?? 'api',
        ]);

        $data = $watermark->watermark_data ?? [];

        // 验证签名（仅 forensic_stealth 算法有签名）
        $valid = true;
        if ($watermark->algorithm === 'forensic_stealth' && !empty($data['signature'])) {
            $signature = $data['signature'];
            unset($data['signature']);
            $expectedSig = hash_hmac('sha256', json_encode($data), config('app.key'));
            $valid = hash_equals($expectedSig, $signature);
        }

        return [
            'found' => true,
            'valid' => $valid,
            'watermark_id' => $watermark->watermark_key,
            'algorithm' => $watermark->algorithm,
            'embedded_at' => $data['embedded_at'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'device_fingerprint' => $data['device_fingerprint'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'extraction_count' => $watermark->extraction_attempts,
            'last_extracted' => $watermark->last_extracted_at,
        ];
    }

    /**
     * 暗水印溯源记录
     */
    public function recordTrace(WatermarkTraceAudit $trace): WatermarkTraceAudit
    {
        return $trace;
    }

    /**
     * 溯源审计列表
     */
    public function listTraces(array $filters = [], int $perPage = 20)
    {
        $query = WatermarkTraceAudit::with(['watermark.license', 'operator'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['watermark_id'])) $query->where('watermark_id', $filters['watermark_id']);
        if (!empty($filters['trace_type'])) $query->where('trace_type', $filters['trace_type']);
        if (!empty($filters['confidence'])) $query->where('confidence', $filters['confidence']);

        return $query->paginate($perPage);
    }

    /**
     * 创建溯源审计
     */
    public function createTrace(array $data): WatermarkTraceAudit
    {
        return WatermarkTraceAudit::create($data);
    }

    /**
     * 水印列表（分页+筛选）
     */
    public function listWatermarks(array $filters = [], int $perPage = 20)
    {
        $query = LicenseWatermark::with(['license:id,license_key,customer_id', 'license.customer:id'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['algorithm'])) $query->where('algorithm', $filters['algorithm']);
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('watermark_key', 'like', "%{$filters['search']}%")
                  ->orWhereHas('license', fn($sq) => $sq->where('license_key', 'like', "%{$filters['search']}%"));
            });
        }

        return $query->paginate($perPage);
    }

    // ═══════════ M3-10 增强功能 ═══════════

    /**
     * 批量嵌入暗水印
     */
    public function batchEmbedForensic(array $licenseIds, array $sourceInfo = []): array
    {
        $results = [];
        $maxBatch = config('watermark.batch.max_licenses_per_batch', 100);

        $licenseIds = array_slice($licenseIds, 0, $maxBatch);
        $licenses = License::whereIn('id', $licenseIds)->get();

        foreach ($licenses as $license) {
            try {
                $watermark = $this->embedForensicWatermark($license, $sourceInfo);
                $results[] = [
                    'license_id' => $license->id,
                    'license_key' => $license->license_key,
                    'watermark_key' => $watermark->watermark_key,
                    'success' => true,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'license_id' => $license->id,
                    'license_key' => $license->license_key,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * 批量提取暗水印
     */
    public function batchExtractForensic(array $licenseIds): array
    {
        $results = [];
        $licenses = License::whereIn('id', $licenseIds)->get();

        foreach ($licenses as $license) {
            try {
                $result = $this->extractAndVerify($license);
                $results[] = [
                    'license_id' => $license->id,
                    'license_key' => $license->license_key,
                    ...$result,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'license_id' => $license->id,
                    'license_key' => $license->license_key,
                    'found' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * 获取水印统计报表
     */
    public function getWatermarkReport(int $days = 30): array
    {
        $since = now()->subDays($days);

        $totalEmbedded = LicenseWatermark::where('created_at', '>=', $since)->count();
        $byAlgorithm = LicenseWatermark::where('created_at', '>=', $since)
            ->selectRaw('algorithm, count(*) as count')
            ->groupBy('algorithm')
            ->pluck('count', 'algorithm')
            ->toArray();

        $byStatus = LicenseWatermark::where('created_at', '>=', $since)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $extractionStats = LicenseWatermark::where('created_at', '>=', $since)
            ->selectRaw('COALESCE(SUM(extraction_attempts), 0) as total_extractions,
                          COUNT(CASE WHEN extraction_attempts > 0 THEN 1 END) as extracted_watermarks')
            ->first();

        $traceStats = WatermarkTraceAudit::where('created_at', '>=', $since)
            ->selectRaw("trace_type, confidence, count(*) as count")
            ->groupBy('trace_type', 'confidence')
            ->get()
            ->groupBy('trace_type')
            ->map(fn($items) => $items->pluck('count', 'confidence')->toArray())
            ->toArray();

        return [
            'period_days' => $days,
            'total_embedded' => $totalEmbedded,
            'by_algorithm' => $byAlgorithm,
            'by_status' => $byStatus,
            'total_extractions' => $extractionStats?->total_extractions ?? 0,
            'extracted_watermarks' => $extractionStats?->extracted_watermarks ?? 0,
            'trace_stats' => $traceStats,
            'recent_traces' => WatermarkTraceAudit::with(['watermark.license', 'operator'])
                ->where('created_at', '>=', $since)
                ->latest()
                ->limit(20)
                ->get()
                ->toArray(),
        ];
    }

    /**
     * 验证日志分析
     */
    public function getVerificationAnalysis(int $days = 30): array
    {
        $since = now()->subDays($days);

        $totalLogs = LicenseVerificationLog::where('created_at', '>=', $since)->count();
        $successCount = LicenseVerificationLog::where('created_at', '>=', $since)
            ->where('status', 'verified')->count();
        $failureCount = LicenseVerificationLog::where('created_at', '>=', $since)
            ->where('status', '!=', 'verified')->count();

        $dailyTrend = LicenseVerificationLog::where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, status, count(*) as count')
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(fn($items) => [
                'date' => $items->first()->date,
                'success' => $items->where('status', 'verified')->sum('count'),
                'failure' => $items->sum('count') - $items->where('status', 'verified')->sum('count'),
                'total' => $items->sum('count'),
            ])
            ->values()
            ->toArray();

        $topFailedLicenses = LicenseVerificationLog::where('created_at', '>=', $since)
            ->where('status', '!=', 'verified')
            ->selectRaw('license_id, count(*) as failure_count')
            ->groupBy('license_id')
            ->orderByDesc('failure_count')
            ->limit(10)
            ->with('license:id,license_key')
            ->get()
            ->toArray();

        $failureByReason = LicenseVerificationLog::where('created_at', '>=', $since)
            ->where('status', '!=', 'verified')
            ->selectRaw('reason, count(*) as count')
            ->groupBy('reason')
            ->orderByDesc('count')
            ->pluck('count', 'reason')
            ->toArray();

        return [
            'period_days' => $days,
            'total_logs' => $totalLogs,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'success_rate' => $totalLogs > 0 ? round(($successCount / $totalLogs) * 100, 2) : 0,
            'daily_trend' => $dailyTrend,
            'top_failed_licenses' => $topFailedLicenses,
            'failure_by_reason' => $failureByReason,
        ];
    }

    /**
     * 泄漏检测扫描（对外部来源搜索水印）
     */
    public function scanForLeaks(string $watermarkKey): array
    {
        $watermark = LicenseWatermark::where('watermark_key', $watermarkKey)->first();
        if (!$watermark) {
            return ['found' => false, 'message' => '水印不存在'];
        }

        $results = [];
        $sources = config('watermark.leak_scan.sources', []);

        // GitHub 搜索
        if ($sources['github']['enabled'] ?? false) {
            try {
                // 实际场景可集成 GitHub API 搜索
                $results['github'] = [
                    'scanned' => true,
                    'matches' => 0,
                    'urls' => [],
                ];
            } catch (\Exception $e) {
                $results['github'] = ['scanned' => true, 'error' => $e->getMessage()];
            }
        }

        // 记录扫描审计
        $trace = WatermarkTraceAudit::create([
            'watermark_id' => $watermark->id,
            'license_id' => $watermark->license_id,
            'trace_type' => 'auto',
            'source' => 'leak_scan',
            'trace_result' => $results,
            'confidence' => 'low',
            'operator_id' => auth()->id(),
        ]);

        return [
            'found' => false,
            'trace_id' => $trace->id,
            'sources_scanned' => array_keys($results),
            'results' => $results,
        ];
    }

    /**
     * 生成水印审计报告
     */
    public function generateAuditReport(int $watermarkId): array
    {
        $watermark = LicenseWatermark::with(['license.customer'])->findOrFail($watermarkId);

        $traces = WatermarkTraceAudit::where('watermark_id', $watermarkId)
            ->with('operator')
            ->latest()
            ->get();

        $extractionHistory = LicenseVerificationLog::where('license_id', $watermark->license_id)
            ->where('created_at', '>=', $watermark->created_at)
            ->latest()
            ->limit(50)
            ->get();

        return [
            'watermark' => $watermark->toArray(),
            'license' => $watermark->license->toArray(),
            'customer' => $watermark->license?->customer?->toArray(),
            'traces' => $traces->toArray(),
            'extraction_history' => $extractionHistory->toArray(),
            'report_generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * 解析水印载荷中的人可读信息
     */
    public function decodeForensicPayload(LicenseWatermark $watermark): array
    {
        $data = $watermark->watermark_data ?? [];

        if (empty($data) || $watermark->algorithm !== 'forensic_stealth') {
            return ['decodable' => false, 'message' => '非暗水印格式'];
        }

        $signature = $data['signature'] ?? '';
        unset($data['signature']);

        $expectedSig = hash_hmac('sha256', json_encode($data), config('app.key'));
        $valid = !empty($signature) && hash_equals($expectedSig, $signature);

        return [
            'decodable' => true,
            'valid_signature' => $valid,
            'payload' => [
                'embedded_at' => $data['embedded_at'] ?? null,
                'license_key_hash' => $data['license_key_hash'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'tenant_id' => $data['tenant_id'] ?? null,
                'device_fingerprint' => $data['device_fingerprint'] ?? null,
                'ip_address' => $data['ip_address'] ?? null,
                'geoip' => $data['geoip'] ?? null,
                'watermark_id' => $data['watermark_id'] ?? null,
            ],
        ];
    }
}

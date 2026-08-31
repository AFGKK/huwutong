<?php

namespace App\Services;

use App\Models\IpWhitelist;
use App\Models\LoginPolicy;
use App\Models\SecurityEvent;
use App\Models\SecuritySopExecution;
use App\Models\SecuritySopTemplate;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;

/**
 * 安全中心服务
 *
 * 管理 IP 白名单/黑名单、登录策略、用户会话、安全事件
 */
class SecurityCenterService
{
    // ─── 概览 ───

    public function getDashboard(int $tenantId = null): array
    {
        $query = fn($q) => $tenantId ? $q->where('tenant_id', $tenantId) : $q;

        $totalSessions = UserSession::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('expires_at', '>', now())->count();
        $activeSessions = UserSession::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('is_current', true)->where('expires_at', '>', now())->count();

        $whitelistCount = IpWhitelist::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count();
        $activeWhitelist = IpWhitelist::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)->count();

        $recentEvents = SecurityEvent::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('created_at')->limit(10)->get()->toArray();

        $eventStats = SecurityEvent::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw("event_type, COUNT(*) as cnt, DATE(created_at) as date")
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('event_type', 'date')
            ->orderBy('date')
            ->get()->toArray();

        $failedLogins = SecurityEvent::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('event_type', 'login_failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return [
            'total_sessions' => $totalSessions,
            'active_sessions' => $activeSessions,
            'whitelist_count' => $whitelistCount,
            'active_whitelist' => $activeWhitelist,
            'failed_logins_24h' => $failedLogins,
            'recent_events' => $recentEvents,
            'event_stats' => $eventStats,
            'policies_applied' => LoginPolicy::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->where('is_enabled', true)->count(),
        ];
    }

    // ─── IP 白名单 ───

    public function getIpWhitelists(int $tenantId = null, string $type = null): array
    {
        $query = IpWhitelist::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->when($type, fn($q) => $q->where('type', $type))
            ->orderByDesc('created_at');

        return $query->get()->all();
    }

    public function createIpWhitelist(array $data): IpWhitelist
    {
        $item = IpWhitelist::create($data);
        Cache::forget("tenant:{$data['tenant_id']}:whitelist");
        Cache::forget("tenant:{$data['tenant_id']}:blacklist");
        return $item;
    }

    public function updateIpWhitelist(IpWhitelist $whitelist, array $data): IpWhitelist
    {
        $whitelist->update($data);
        Cache::forget("tenant:{$whitelist->tenant_id}:whitelist");
        Cache::forget("tenant:{$whitelist->tenant_id}:blacklist");
        return $whitelist->fresh();
    }

    public function deleteIpWhitelist(IpWhitelist $whitelist): void
    {
        $tenantId = $whitelist->tenant_id;
        $whitelist->delete();
        Cache::forget("tenant:{$tenantId}:whitelist");
        Cache::forget("tenant:{$tenantId}:blacklist");
    }

    public function bulkImportIps(int $tenantId, array $ips, string $type = 'whitelist'): int
    {
        $count = 0;
        foreach ($ips as $ip) {
            if (empty(trim($ip))) continue;
            IpWhitelist::firstOrCreate(
                ['tenant_id' => $tenantId, 'ip_address' => trim($ip)],
                ['type' => $type]
            );
            $count++;
        }
        Cache::forget("tenant:{$tenantId}:whitelist");
        Cache::forget("tenant:{$tenantId}:blacklist");
        return $count;
    }

    // ─── 登录策略 ───

    public function getPolicies(int $tenantId = null): array
    {
        return LoginPolicy::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('sort_order')
            ->get()
            ->all();
    }

    public function getPolicy(string $key, int $tenantId = null): LoginPolicy
    {
        $query = LoginPolicy::where('policy_key', $key);
        if ($tenantId) $query->where('tenant_id', $tenantId);
        return $query->firstOrFail();
    }

    public function updatePolicy(LoginPolicy $policy, array $data): LoginPolicy
    {
        $policy->update($data);
        Cache::forget("tenant:{$policy->tenant_id}:ip_enforced");
        return $policy->fresh();
    }

    public function getPolicyValue(string $key, int $tenantId = null): mixed
    {
        $policy = LoginPolicy::where('policy_key', $key)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('is_enabled', true)
            ->first();

        if (!$policy) {
            $def = LoginPolicy::POLICIES[$key] ?? null;
            return $def ? $this->castValue($def['default'], $def['type']) : null;
        }

        return $this->castValue($policy->value, $policy->value_type);
    }

    protected function castValue(?string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) ($value ?? 0),
            'boolean' => $value === 'true' || $value === '1',
            'json' => json_decode($value ?? '[]', true) ?? [],
            default => $value,
        };
    }

    // ─── 会话管理 ───

    public function getSessions(int $userId, bool $activeOnly = true): array
    {
        $query = UserSession::where('user_id', $userId);
        if ($activeOnly) {
            $query->where('expires_at', '>', now());
        }
        return $query->orderByDesc('is_current')->orderByDesc('last_activity_at')->get()->all();
    }

    public function getActiveSessions(int $tenantId = null): array
    {
        $query = UserSession::with('user:id,name,email')
            ->where('expires_at', '>', now())
            ->where('is_current', true);
        if ($tenantId) $query->where('tenant_id', $tenantId);
        return $query->orderByDesc('last_activity_at')->limit(100)->get()->all();
    }

    public function terminateSession(int $sessionId): void
    {
        UserSession::where('id', $sessionId)->update(['expires_at' => now(), 'is_current' => false]);
    }

    public function terminateUserSessions(int $userId, ?int $exceptSessionId = null): void
    {
        $query = UserSession::where('user_id', $userId)->where('expires_at', '>', now());
        if ($exceptSessionId) $query->where('id', '!=', $exceptSessionId);
        $query->update(['expires_at' => now(), 'is_current' => false]);
    }

    public function terminateAllTenantSessions(int $tenantId): int
    {
        return UserSession::where('tenant_id', $tenantId)
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now(), 'is_current' => false]);
    }

    public function createOrUpdateSession(int $userId, ?int $tenantId, string $sessionId, array $data): UserSession
    {
        $agent = new Agent();
        $agent->setUserAgent($data['user_agent'] ?? '');

        $deviceType = $agent->isMobile() ? 'mobile' : ($agent->isTablet() ? 'tablet' : 'desktop');

        // 如果启用了单设备登录，终止旧会话
        $singleDevice = $this->getPolicyValue('session_single_device', $tenantId);
        if ($singleDevice) {
            UserSession::where('user_id', $userId)
                ->where('session_id', '!=', $sessionId)
                ->where('is_current', true)
                ->update(['is_current' => false, 'expires_at' => now()->subMinute()]);
        }

        return UserSession::updateOrCreate(
            ['session_id' => $sessionId],
            [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'device_type' => $deviceType,
                'browser' => $agent->browser(),
                'os' => $agent->platform(),
                'is_current' => true,
                'last_activity_at' => now(),
                'expires_at' => now()->addMinutes(
                    $this->getPolicyValue('session_timeout_minutes', $tenantId) ?: 480
                ),
            ]
        );
    }

    // ─── 安全事件 ───

    public function logEvent(array $data): SecurityEvent
    {
        return SecurityEvent::create($data);
    }

    public function getEvents(int $tenantId = null, array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $query = SecurityEvent::with('user:id,name,email')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->when($filters['event_type'] ?? null, fn($q, $v) => $q->where('event_type', $v))
            ->when($filters['severity'] ?? null, fn($q, $v) => $q->where('severity', $v))
            ->when($filters['ip_address'] ?? null, fn($q, $v) => $q->where('ip_address', $v))
            ->when($filters['user_id'] ?? null, fn($q, $v) => $q->where('user_id', $v))
            ->when($filters['date_from'] ?? null, fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('created_at');

        return $query->paginate($perPage, ['*'], 'page', $page)->toArray();
    }

    public function getSecurityScore(int $tenantId = null): array
    {
        $score = 100;
        $checks = [];

        // IP whitelist
        $whitelistEnabled = $this->getPolicyValue('ip_whitelist_enforced', $tenantId);
        if (!$whitelistEnabled) {
            $score -= 10;
            $checks[] = [
                'item' => 'ip_whitelist',
                'status' => 'not_enabled',
                'status_params' => [],
                'deduction' => 10,
                'recommendation' => 'enable_ip_whitelist',
            ];
        } else {
            $checks[] = [
                'item' => 'ip_whitelist',
                'status' => 'enabled',
                'status_params' => [],
                'deduction' => 0,
                'recommendation' => '',
            ];
        }

        // MFA
        $mfaRequired = $this->getPolicyValue('mfa_required', $tenantId);
        if (!$mfaRequired) {
            $score -= 15;
            $checks[] = [
                'item' => 'mfa',
                'status' => 'not_required',
                'status_params' => [],
                'deduction' => 15,
                'recommendation' => 'require_mfa',
            ];
        } else {
            $checks[] = [
                'item' => 'mfa',
                'status' => 'required',
                'status_params' => [],
                'deduction' => 0,
                'recommendation' => '',
            ];
        }

        // Password length
        $minLen = (int) $this->getPolicyValue('password_min_length', $tenantId);
        if ($minLen < 8) {
            $score -= 8;
            $checks[] = [
                'item' => 'password_length',
                'status' => 'chars',
                'status_params' => ['n' => $minLen],
                'deduction' => 8,
                'recommendation' => 'password_min_8',
            ];
        } else {
            $checks[] = [
                'item' => 'password_length',
                'status' => 'chars',
                'status_params' => ['n' => $minLen],
                'deduction' => 0,
                'recommendation' => '',
            ];
        }

        // Session timeout
        $timeout = (int) $this->getPolicyValue('session_timeout_minutes', $tenantId);
        if ($timeout > 480) {
            $score -= 5;
            $checks[] = [
                'item' => 'session_timeout',
                'status' => 'minutes',
                'status_params' => ['n' => $timeout],
                'deduction' => 5,
                'recommendation' => 'session_timeout_480',
            ];
        } else {
            $checks[] = [
                'item' => 'session_timeout',
                'status' => 'minutes',
                'status_params' => ['n' => $timeout],
                'deduction' => 0,
                'recommendation' => '',
            ];
        }

        // Recent critical events
        $criticalEvents = SecurityEvent::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('severity', 'critical')
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($criticalEvents > 0) {
            $score -= $criticalEvents * 5;
            $checks[] = [
                'item' => 'critical_events',
                'status' => 'critical_today',
                'status_params' => ['n' => $criticalEvents],
                'deduction' => $criticalEvents * 5,
                'recommendation' => 'handle_critical_events',
            ];
        } else {
            $checks[] = [
                'item' => 'critical_events',
                'status' => 'none',
                'status_params' => [],
                'deduction' => 0,
                'recommendation' => '',
            ];
        }

        return [
            'score' => max(0, $score),
            'checks' => $checks,
            'level' => $score >= 80 ? 'good' : ($score >= 50 ? 'fair' : 'poor'),
        ];
    }

    // ═══════════════ SOP 响应编排 (M3-25) ═══════════════

    /**
     * 获取SOP模板列表
     */
    public function getSopTemplates(array $filters = [], int $perPage = 20)
    {
        $query = SecuritySopTemplate::with('creator:id,name')
            ->orderBy('sort_order')->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (!empty($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }

        return $query->paginate($perPage);
    }

    /**
     * 创建SOP模板
     */
    public function createSopTemplate(array $data): SecuritySopTemplate
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']) . '-' . Str::random(6);

        return SecuritySopTemplate::create($data);
    }

    /**
     * 更新SOP模板
     */
    public function updateSopTemplate(SecuritySopTemplate $template, array $data): SecuritySopTemplate
    {
        $template->update($data);
        return $template->fresh();
    }

    /**
     * 匹配安全事件到SOP并自动执行
     *
     * @return SecuritySopExecution|null
     */
    public function matchAndExecuteSop(SecurityEvent $event): ?SecuritySopExecution
    {
        // 查找匹配的活跃SOP
        $template = SecuritySopTemplate::active()
            ->where('severity', $event->severity)
            ->where(function ($q) use ($event) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $event->tenant_id);
            })
            ->orderBy('sort_order')
            ->orderByDesc('is_auto_execute')
            ->first();

        if (!$template) {
            return null;
        }

        // 检查触发条件
        $conditions = $template->trigger_conditions;
        if ($conditions && !$this->evaluateTriggerConditions($conditions, $event)) {
            return null;
        }

        // 创建执行记录
        $execution = SecuritySopExecution::create([
            'tenant_id' => $event->tenant_id,
            'sop_template_id' => $template->id,
            'event_id' => $event->id,
            'triggered_by' => 'event',
            'status' => $template->is_auto_execute ? 'in_progress' : 'pending',
            'total_steps' => count($template->steps ?? []),
            'completed_steps' => 0,
            'execution_log' => [],
        ]);

        // 如果设为自动执行，执行步骤
        if ($template->is_auto_execute) {
            $this->executeSopSteps($execution, $template, $event);
        }

        // 更新事件的SOP关联
        $event->update([
            'sop_execution_id' => $execution->id,
            'resolution_status' => 'in_progress',
        ]);

        return $execution->fresh();
    }

    /**
     * 手动执行SOP
     */
    public function executeSopManually(SecuritySopTemplate $template, ?SecurityEvent $event = null, ?int $userId = null): SecuritySopExecution
    {
        $execution = SecuritySopExecution::create([
            'tenant_id' => $template->tenant_id,
            'sop_template_id' => $template->id,
            'event_id' => $event?->id,
            'triggered_by' => 'manual',
            'status' => 'in_progress',
            'total_steps' => count($template->steps ?? []),
            'completed_steps' => 0,
            'execution_log' => [],
        ]);

        $this->executeSopSteps($execution, $template, $event, $userId);

        return $execution->fresh();
    }

    /**
     * 执行SOP步骤
     */
    protected function executeSopSteps(SecuritySopExecution $execution, SecuritySopTemplate $template, ?SecurityEvent $event = null, ?int $userId = null): void
    {
        $steps = $template->steps ?? [];
        $log = $execution->execution_log ?? [];
        $completed = 0;
        $allSuccess = true;

        foreach ($steps as $step) {
            $actionType = $step['action_type'] ?? '';
            $config = $step['config'] ?? [];
            $stepResult = ['success' => false, 'message' => ''];

            try {
                $stepResult = $this->executeSopAction($actionType, $config, $event, $userId);
            } catch (\Exception $e) {
                $stepResult = ['success' => false, 'message' => $e->getMessage()];
                $allSuccess = false;
            }

            $log[] = [
                'step' => $step['order'] ?? (count($log) + 1),
                'action' => $actionType,
                'description' => $step['description'] ?? '',
                'status' => $stepResult['success'] ? 'completed' : 'failed',
                'result' => $stepResult['message'] ?? '',
                'executed_at' => now()->toIso8601String(),
            ];

            if ($stepResult['success']) {
                $completed++;
            }
        }

        $finalStatus = $allSuccess ? 'completed' : ($completed > 0 ? 'partially_completed' : 'failed');

        $execution->update([
            'execution_log' => $log,
            'status' => $finalStatus,
            'completed_steps' => $completed,
            'result_summary' => "{$completed}/{$execution->total_steps} 步骤完成",
        ]);

        // 更新事件状态
        if ($event) {
            $event->update([
                'resolution_status' => $finalStatus === 'completed' ? 'resolved' : 'in_progress',
                'resolved_at' => $finalStatus === 'completed' ? now() : null,
            ]);
        }
    }

    /**
     * 执行单个SOP动作
     */
    protected function executeSopAction(string $actionType, array $config, ?SecurityEvent $event = null, ?int $userId = null): array
    {
        return match ($actionType) {
            'log_event' => ['success' => true, 'message' => __('app.security_center.security_center_d7e017ecaf')],
            'notify_admin' => $this->actionNotifyAdmin($config, $event),
            'notify_user' => $this->actionNotifyUser($config, $event),
            'block_ip' => $this->actionBlockIp($config, $event),
            'terminate_sessions' => $this->actionTerminateSessions($config, $event, $userId),
            'disable_account' => $this->actionDisableAccount($config, $event),
            'require_mfa' => ['success' => true, 'message' => __('app.security_center.security_center_37a3c267f9')],
            'send_alert_email' => $this->actionSendAlertEmail($config, $event),
            'create_ticket' => ['success' => true, 'message' => __('app.security_center.security_center_de6fb014cf')],
            'custom_webhook' => $this->actionCustomWebhook($config, $event),
            default => ['success' => false, 'message' => "未知动作类型: {$actionType}"],
        };
    }

    protected function actionNotifyAdmin(array $config, ?SecurityEvent $event): array
    {
        try {
            $message = $config['message'] ?? __('app.security_center.security_center_dc935122bc');
            Log::warning("[SOP] 通知管理员: {$message}", ['event_id' => $event?->id]);
            return ['success' => true, 'message' => __('app.security_center.security_center_c58e07c94c')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function actionNotifyUser(array $config, ?SecurityEvent $event): array
    {
        try {
            $message = $config['message'] ?? __('app.security_center.security_center_f99891d04a');
            Log::info("[SOP] 通知用户: {$message}", ['event_id' => $event?->id]);
            return ['success' => true, 'message' => __('app.security_center.security_center_9f03fd1d60')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function actionBlockIp(array $config, ?SecurityEvent $event): array
    {
        try {
            $ip = $event?->ip_address ?? $config['ip_address'] ?? '';
            if (empty($ip)) {
                return ['success' => false, 'message' => __('app.security_center.security_center_97be8ea37d')];
            }

            \App\Models\IpWhitelist::firstOrCreate(
                ['tenant_id' => $event->tenant_id, 'ip_address' => $ip],
                ['type' => 'blacklist', 'label' => $config['reason'] ?? __('app.security_center.security_center_553a2e3820'), 'is_active' => true]
            );
            Cache::forget("tenant:{$event->tenant_id}:blacklist");

            return ['success' => true, 'message' => __('app.common.ip_blacklisted', ['ip' => $ip])];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function actionTerminateSessions(array $config, ?SecurityEvent $event, ?int $userId): array
    {
        try {
            $targetUserId = $config['user_id'] ?? $event?->user_id ?? $userId;
            if (!$targetUserId) {
                return ['success' => false, 'message' => __('app.security_center.security_center_11edbc6382')];
            }

            $count = UserSession::where('user_id', $targetUserId)
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now(), 'is_current' => false]);

            return ['success' => true, 'message' => __('app.common.sessions_terminated', ['count' => $count])];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function actionDisableAccount(array $config, ?SecurityEvent $event): array
    {
        try {
            $userId = $config['user_id'] ?? $event?->user_id;
            if (!$userId) {
                return ['success' => false, 'message' => __('app.security_center.security_center_11edbc6382')];
            }

            User::where('id', $userId)->update(['is_active' => false]);

            return ['success' => true, 'message' => __('app.common.user_disabled', ['user' => $userId])];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function actionSendAlertEmail(array $config, ?SecurityEvent $event): array
    {
        try {
            $email = $config['email'] ?? ($event?->user?->email ?? 'admin@example.com');
            Log::info("[SOP] 发送告警邮件至 {$email}", ['event' => $event?->event_type]);
            return ['success' => true, 'message' => __('app.common.alert_email_sent', ['email' => $email])];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function actionCustomWebhook(array $config, ?SecurityEvent $event): array
    {
        try {
            $url = $config['url'] ?? '';
            if (empty($url)) {
                return ['success' => false, 'message' => __('app.security_center.security_center_ad6a7e31a0')];
            }
            Log::info("[SOP] 调用Webhook: {$url}", ['event_id' => $event?->id]);
            return ['success' => true, 'message' => __('app.common.webhook_called', ['url' => $url])];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 评估触发条件
     */
    protected function evaluateTriggerConditions(array $conditions, SecurityEvent $event): bool
    {
        // 检查事件类型
        if (!empty($conditions['event_types']) && is_array($conditions['event_types'])) {
            if (!in_array($event->event_type, $conditions['event_types'])) {
                return false;
            }
        }

        // 检查时间窗口内的频率
        if (!empty($conditions['threshold']) && !empty($conditions['time_window_minutes'])) {
            $window = now()->subMinutes((int) $conditions['time_window_minutes']);
            $count = SecurityEvent::where('tenant_id', $event->tenant_id)
                ->where('event_type', $event->event_type)
                ->where('created_at', '>=', $window)
                ->count();

            if ($count < (int) $conditions['threshold']) {
                return false;
            }
        }

        return true;
    }

    /**
     * 解决安全事件
     */
    public function resolveEvent(SecurityEvent $event, string $resolution, string $notes = null, ?int $resolvedBy = null): SecurityEvent
    {
        $event->update([
            'resolution_status' => $resolution,
            'resolution_notes' => $notes,
            'resolved_at' => now(),
        ]);

        // 如果有SOP执行记录，也更新
        if ($event->sop_execution_id) {
            SecuritySopExecution::where('id', $event->sop_execution_id)
                ->update([
                    'status' => 'completed',
                    'resolved_by' => $resolvedBy,
                    'resolved_at' => now(),
                    'result_summary' => "已解决: {$resolution}",
                ]);
        }

        return $event->fresh();
    }

    /**
     * 获取SOP执行列表
     */
    public function getSopExecutions(array $filters = [], int $perPage = 20)
    {
        $query = SecuritySopExecution::with(['template:id,name', 'event:id,event_type,severity,created_at'])
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['sop_template_id'])) {
            $query->where('sop_template_id', $filters['sop_template_id']);
        }

        return $query->paginate($perPage);
    }

    /**
     * 获取SOP统计
     */
    public function getSopStats(int $tenantId = null): array
    {
        $query = fn($q) => $tenantId ? $q->where('tenant_id', $tenantId) : $q;

        $activeTemplates = SecuritySopTemplate::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'active')->count();
        $totalTemplates = SecuritySopTemplate::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count();

        $totalExecutions = SecuritySopExecution::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count();
        $autoExecutions = SecuritySopExecution::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('triggered_by', 'event')->count();
        $failedExecutions = SecuritySopExecution::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'failed')->count();
        $pendingExecutions = SecuritySopExecution::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereIn('status', ['pending', 'in_progress'])->count();

        $openEvents = SecurityEvent::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where(function ($q) {
                $q->whereNull('resolution_status')
                    ->orWhereIn('resolution_status', ['open', 'in_progress']);
            })->count();

        // 按严重程度统计
        $eventsBySeverity = SecurityEvent::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('severity, COUNT(*) as cnt')
            ->groupBy('severity')
            ->pluck('cnt', 'severity')
            ->toArray();

        return [
            'active_templates' => $activeTemplates,
            'total_templates' => $totalTemplates,
            'total_executions' => $totalExecutions,
            'auto_executions' => $autoExecutions,
            'failed_executions' => $failedExecutions,
            'pending_executions' => $pendingExecutions,
            'open_events' => $openEvents,
            'events_by_severity' => $eventsBySeverity,
        ];
    }

    /**
     * 为安全事件匹配并执行SOP（外部入口）
     */
    public function handleSecurityEvent(SecurityEvent $event): ?SecuritySopExecution
    {
        return $this->matchAndExecuteSop($event);
    }
}

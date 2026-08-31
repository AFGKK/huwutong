<?php

namespace App\Services;

use App\Models\SecuritySopTemplate;
use App\Models\SecuritySopExecution;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * 安全事件响应 SOP 自动化服务 (M3-25)
 *
 * 安全事件完整响应流程：通报→止损→取证→修复→复盘
 * 支持自动化响应剧本执行，对接 SecurityEvent 事件系统
 * 和 SecuritySopTemplate/SecuritySopExecution 模型。
 */
class IncidentResponseService
{
    // ─── SOP 模板管理 ───

    /**
     * 获取 SOP 模板列表
     */
    public function getTemplates(int $tenantId = null, string $severity = null): array
    {
        $query = SecuritySopTemplate::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->when($severity, fn($q) => $q->where('severity', $severity))
            ->orderBy('sort_order')
            ->orderByDesc('created_at');

        return $query->get()->toArray();
    }

    /**
     * 创建 SOP 模板
     */
    public function createTemplate(array $data): SecuritySopTemplate
    {
        $data['slug'] ??= str($data['name'])->slug();
        return DB::transaction(fn() => SecuritySopTemplate::create($data));
    }

    /**
     * 更新 SOP 模板
     */
    public function updateTemplate(SecuritySopTemplate $template, array $data): SecuritySopTemplate
    {
        $template->update($data);
        return $template->fresh();
    }

    /**
     * 删除 SOP 模板
     */
    public function deleteTemplate(SecuritySopTemplate $template): void
    {
        $template->delete();
    }

    // ─── SOP 执行 ───

    /**
     * 根据安全事件自动触发匹配的 SOP
     */
    public function autoTrigger(SecurityEvent $event): ?SecuritySopExecution
    {
        $matched = SecuritySopTemplate::active()
            ->where('is_auto_execute', true)
            ->get()
            ->first(fn(SecuritySopTemplate $tpl) => $this->matchTriggerCondition($tpl, $event));

        if (! $matched) {
            Log::info('无匹配SOP模板', ['event_id' => $event->id, 'event_type' => $event->event_type]);
            return null;
        }

        return $this->executeSop($matched, $event);
    }

    /**
     * 手动执行指定 SOP
     */
    public function manualExecute(SecuritySopTemplate $template, SecurityEvent $event, int $triggeredBy): SecuritySopExecution
    {
        return $this->executeSop($template, $event, $triggeredBy);
    }

    /**
     * 执行 SOP（核心逻辑）
     */
    protected function executeSop(SecuritySopTemplate $template, SecurityEvent $event, ?int $triggeredBy = null): SecuritySopExecution
    {
        $execution = SecuritySopExecution::create([
            'tenant_id' => $event->tenant_id ?? $template->tenant_id,
            'sop_template_id' => $template->id,
            'event_id' => $event->id,
            'triggered_by' => $triggeredBy,
            'status' => 'in_progress',
            'total_steps' => count($template->steps ?? []),
            'completed_steps' => 0,
            'execution_log' => [],
        ]);

        $log = [];

        try {
            foreach ($template->steps ?? [] as $index => $step) {
                $stepResult = $this->executeStep($step, $event);
                $log[] = [
                    'step_index' => $index,
                    'step_type' => $step['type'] ?? 'unknown',
                    'action' => $step['action'] ?? '',
                    'status' => $stepResult['status'],
                    'message' => $stepResult['message'] ?? '',
                    'executed_at' => now()->toIso8601String(),
                ];

                $execution->update([
                    'completed_steps' => $index + 1,
                    'execution_log' => $log,
                ]);

                // 如果某步失败且不可跳过，终止执行
                if ($stepResult['status'] === 'failed' && ! ($step['skippable'] ?? false)) {
                    break;
                }
            }

            $allCompleted = $execution->completed_steps >= $execution->total_steps;
            $execution->update([
                'status' => $allCompleted ? 'completed' : 'partial',
                'result_summary' => $this->buildSummary($log),
            ]);
        } catch (\Throwable $e) {
            Log::error('SOP执行异常', [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
            ]);

            $log[] = [
                'step_index' => count($log),
                'step_type' => 'error',
                'action' => __('app.incident_response.system_error'),
                'status' => 'error',
                'message' => $e->getMessage(),
                'executed_at' => now()->toIso8601String(),
            ];

            $execution->update([
                'status' => 'failed',
                'execution_log' => $log,
                'result_summary' => __('app.incident_response.execution_error') . ': ' . $e->getMessage(),
            ]);
        }

        return $execution->fresh();
    }

    /**
     * 执行单个步骤
     */
    protected function executeStep(array $step, SecurityEvent $event): array
    {
        $type = $step['type'] ?? 'log_event';

        return match ($type) {
            'log_event' => $this->stepLogEvent($step, $event),
            'notify_admin' => $this->stepNotifyAdmin($step, $event),
            'notify_user' => $this->stepNotifyUser($step, $event),
            'block_ip' => $this->stepBlockIp($step, $event),
            'terminate_sessions' => $this->stepTerminateSessions($step, $event),
            'disable_account' => $this->stepDisableAccount($step, $event),
            'require_mfa' => $this->stepRequireMfa($step, $event),
            'send_alert_email' => $this->stepSendAlertEmail($step, $event),
            'create_ticket' => $this->stepCreateTicket($step, $event),
            'custom_webhook' => $this->stepCustomWebhook($step, $event),
            default => ['status' => 'skipped', 'message' => __('app.incident_response.unknown_step_type', ['type' => $type])],
        };
    }

    /**
     * 判断事件是否匹配模板触发条件
     */
    protected function matchTriggerCondition(SecuritySopTemplate $template, SecurityEvent $event): bool
    {
        $conditions = $template->trigger_conditions ?? [];

        foreach ($conditions as $key => $value) {
            $eventValue = $event->{$key} ?? $event->metadata[$key] ?? null;

            if (is_array($value)) {
                if (! in_array($eventValue, $value, true)) {
                    return false;
                }
            } elseif ($eventValue !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * 构建执行摘要
     */
    protected function buildSummary(array $log): string
    {
        $success = count(array_filter($log, fn($l) => $l['status'] === 'success'));
        $failed = count(array_filter($log, fn($l) => in_array($l['status'], ['failed', 'error'], true)));
        $total = count($log);

        return __('app.incident_response.steps_summary', ['total' => $total, 'success' => $success, 'failed' => $failed]);
    }

    // ─── 步骤执行器 ───

    protected function stepLogEvent(array $step, SecurityEvent $event): array
    {
        Log::info("[SOP] 安全事件记录: {$event->id} - {$event->event_type}", [
            'event_id' => $event->id,
            'detail' => $step['detail'] ?? $event->toArray(),
        ]);
        return ['status' => 'success', 'message' => __('app.incident_response.event_logged')];
    }

    protected function stepNotifyAdmin(array $step, SecurityEvent $event): array
    {
        $admins = User::role('admin')->where('tenant_id', $event->tenant_id)->get();
        $message = $step['message'] ?? __('app.incident_response.security_event_type', ['type' => $event->event_type]);

        // 触发通知中心发送（委派给 NotificationService）
        foreach ($admins as $admin) {
            event(new \App\Events\SecurityAlertEvent($admin, $event, $message));
        }

        return ['status' => 'success', 'message' => __('app.incident_response.admin_notified_count', ['count' => $admins->count()])];
    }

    protected function stepNotifyUser(array $step, SecurityEvent $event): array
    {
        if (! $event->user_id) {
            return ['status' => 'skipped', 'message' => __('app.incident_response.event_no_user')];
        }

        $user = User::find($event->user_id);
        if (! $user) {
            return ['status' => 'skipped', 'message' => __('app.incident_response.user_not_found')];
        }

        event(new \App\Events\SecurityAlertEvent($user, $event, $step['message'] ?? __('app.incident_response.security_alert')));
        return ['status' => 'success', 'message' => __('app.incident_response.user_notified', ['email' => $user->email])];
    }

    protected function stepBlockIp(array $step, SecurityEvent $event): array
    {
        $ip = $event->ip_address ?? request()->ip();
        if (! $ip) {
            return ['status' => 'skipped', 'message' => __('app.incident_response.no_ip_to_block')];
        }

        app(SecurityCenterService::class)->createIpWhitelist([
            'tenant_id' => $event->tenant_id,
            'type' => 'blacklist',
            'ip_address' => $ip,
            'reason' => $step['reason'] ?? __('app.incident_response.sop_auto_block_reason', ['type' => $event->event_type]),
            'is_active' => true,
            'expires_at' => now()->addHours($step['block_hours'] ?? 24),
        ]);

        return ['status' => 'success', 'message' => __('app.incident_response.ip_blocked', ['ip' => $ip])];
    }

    protected function stepTerminateSessions(array $step, SecurityEvent $event): array
    {
        $userId = $event->user_id;
        if (! $userId) {
            return ['status' => 'skipped', 'message' => __('app.incident_response.event_no_user')];
        }

        $terminated = \App\Models\UserSession::where('user_id', $userId)
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        return ['status' => 'success', 'message' => __('app.incident_response.sessions_terminated', ['count' => $terminated])];
    }

    protected function stepDisableAccount(array $step, SecurityEvent $event): array
    {
        $userId = $event->user_id;
        if (! $userId) {
            return ['status' => 'skipped', 'message' => __('app.incident_response.event_no_user')];
        }

        $user = User::find($userId);
        if (! $user) {
            return ['status' => 'skipped', 'message' => __('app.incident_response.user_not_found')];
        }

        $user->update(['is_active' => false]);
        return ['status' => 'success', 'message' => __('app.incident_response.account_disabled', ['email' => $user->email])];
    }

    protected function stepRequireMfa(array $step, SecurityEvent $event): array
    {
        $userId = $event->user_id;
        if (! $userId) {
            return ['status' => 'skipped', 'message' => __('app.incident_response.event_no_user')];
        }

        // 标记用户需强制 MFA 验证
        Cache::put("force_mfa:{$userId}", true, now()->addDays(7));
        return ['status' => 'success', 'message' => __('app.incident_response.user_mfa_required', ['id' => $userId])];
    }

    protected function stepSendAlertEmail(array $step, SecurityEvent $event): array
    {
        // 委派给邮件服务发送告警
        try {
            \Illuminate\Support\Facades\Mail::send(
                $step['template'] ?? 'emails.security-alert',
                ['event' => $event, 'message' => $step['message'] ?? ''],
                fn($mail) => $mail->to($step['to'] ?? config('mail.security_alert_to'))
                    ->subject($step['subject'] ?? ('[' . __('app.incident_response.security_alert_subject_prefix') . '] ' . $event->event_type)),
            );
            return ['status' => 'success', 'message' => __('app.incident_response.alert_email_sent')];
        } catch (\Throwable $e) {
            Log::error('SOP告警邮件发送失败', ['error' => $e->getMessage()]);
            return ['status' => 'failed', 'message' => __('app.incident_response.email_send_failed') . ': ' . $e->getMessage()];
        }
    }

    protected function stepCreateTicket(array $step, SecurityEvent $event): array
    {
        // 创建安全工单（委派给 TicketService）
        try {
            $ticket = app(\App\Services\TicketService::class)->create([
                'tenant_id' => $event->tenant_id,
                'title' => $step['title'] ?? ('[' . __('app.incident_response.security_event_title_prefix') . '] ' . $event->event_type),
                'description' => $step['description'] ?? __('app.incident_response.auto_created_by_sop', ['event_id' => $event->id, 'type' => $event->event_type]),
                'priority' => $step['priority'] ?? 'high',
                'category' => 'security',
            ]);
            return ['status' => 'success', 'message' => __('app.incident_response.ticket_created', ['id' => $ticket->id])];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'message' => __('app.incident_response.ticket_create_failed') . ': ' . $e->getMessage()];
        }
    }

    protected function stepCustomWebhook(array $step, SecurityEvent $event): array
    {
        $url = $step['webhook_url'] ?? null;
        if (! $url) {
            return ['status' => 'skipped', 'message' => __('app.incident_response.webhook_url_not_configured')];
        }

        try {
            \Illuminate\Support\Facades\Http::timeout(10)
                ->withHeaders(['X-SOP-Event' => $event->event_type])
                ->post($url, [
                    'event_id' => $event->id,
                    'event_type' => $event->event_type,
                    'tenant_id' => $event->tenant_id,
                    'timestamp' => now()->toIso8601String(),
                    'payload' => $step['payload'] ?? $event->toArray(),
                ]);
            return ['status' => 'success', 'message' => __('app.incident_response.webhook_pushed', ['url' => $url])];
        } catch (\Throwable $e) {
            Log::warning('SOP Webhook 推送失败', ['url' => $url, 'error' => $e->getMessage()]);
            return ['status' => 'failed', 'message' => __('app.incident_response.webhook_push_failed')];
        }
    }

    // ─── 执行记录查询 ───

    /**
     * 获取执行历史
     */
    public function getExecutionHistory(int $tenantId = null, array $filters = []): array
    {
        $query = SecuritySopExecution::with(['template:id,name', 'event:id,event_type'])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->when($filters['status'] ?? null, fn($q) => $q->where('status', $filters['status']))
            ->when($filters['sop_template_id'] ?? null, fn($q) => $q->where('sop_template_id', $filters['sop_template_id']))
            ->when($filters['date_from'] ?? null, fn($q) => $q->where('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn($q) => $q->where('created_at', '<=', $filters['date_to']))
            ->orderByDesc('created_at');

        return $query->paginate($filters['per_page'] ?? 20)->toArray();
    }

    /**
     * 获取执行详情
     */
    public function getExecutionDetail(int $executionId): ?SecuritySopExecution
    {
        return SecuritySopExecution::with(['template', 'event', 'resolver'])->find($executionId);
    }

    /**
     * 标记执行结果为已解决
     */
    public function resolveExecution(SecuritySopExecution $execution, int $resolvedBy, string $notes = ''): SecuritySopExecution
    {
        $execution->update([
            'status' => 'resolved',
            'resolved_by' => $resolvedBy,
            'resolved_at' => now(),
            'result_summary' => ($execution->result_summary ?? '') . __('app.incident_response.resolved_note', ['notes' => $notes]),
        ]);

        return $execution->fresh();
    }

    // ─── 仪表盘 ───

    /**
     * 安全事件响应仪表盘数据
     */
    public function getDashboard(int $tenantId = null): array
    {
        $baseQuery = SecuritySopExecution::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));
        $recentQuery = fn($days) => clone $baseQuery->where('created_at', '>=', now()->subDays($days));

        return [
            'total_executions' => $baseQuery->count(),
            'recent_24h' => $recentQuery(1)->count(),
            'recent_7d' => $recentQuery(7)->count(),
            'status_breakdown' => $baseQuery->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')->pluck('cnt', 'status')->toArray(),
            'success_rate' => $baseQuery->where('status', 'completed')->count() / max($baseQuery->count(), 1) * 100,
            'recent_10' => $baseQuery->with('template:id,name')->orderByDesc('created_at')->limit(10)->get()->toArray(),
            'active_templates' => SecuritySopTemplate::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->active()->count(),
        ];
    }
}

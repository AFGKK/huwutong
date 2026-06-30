<?php

namespace App\Services;

use App\Models\AutomationActionLog;
use App\Models\AutomationExecutionLog;
use App\Models\AutomationRule;
use App\Models\AutomationWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 自动化规则引擎服务
 *
 * 提供：
 * - 规则 CRUD
 * - 规则评估（触发条件匹配）
 * - 动作执行（内置动作 + Webhook）
 * - 执行历史追踪
 * - 速率限制与冷却
 */
class AutomationRuleService
{
    // ─── 规则 CRUD ───

    public function getRules(array $filters = [], int $tenantId = null): array
    {
        $query = AutomationRule::withCount('executions')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->when(!$tenantId, fn($q) => $q->whereNull('tenant_id'));

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['trigger_type'])) {
            $query->where('trigger_type', $filters['trigger_type']);
        }
        if (!empty($filters['search'])) {
            $q = $filters['search'];
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        return $query->orderByDesc('priority')
            ->orderByDesc('updated_at')
            ->paginate(min((int) ($filters['per_page'] ?? 50), 100))
            ->toArray();
    }

    public function getRule(int $id): AutomationRule
    {
        return AutomationRule::with(['executions' => fn($q) => $q->latest()->limit(10), 'webhooks'])->findOrFail($id);
    }

    public function createRule(array $data): AutomationRule
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']) . '-' . Str::random(6);
        return AutomationRule::create($data);
    }

    public function updateRule(AutomationRule $rule, array $data): AutomationRule
    {
        $rule->update($data);
        return $rule->fresh();
    }

    public function deleteRule(AutomationRule $rule): void
    {
        $rule->executions()->delete();
        $rule->webhooks()->detach();
        $rule->delete();
    }

    public function toggleStatus(AutomationRule $rule): AutomationRule
    {
        $newStatus = match ($rule->status) {
            'active' => 'paused',
            'paused' => 'active',
            'draft' => 'active',
            default => 'active',
        };
        $rule->update(['status' => $newStatus]);
        return $rule->fresh();
    }

    // ─── Webhook 端点 ───

    public function getWebhooks(int $tenantId): array
    {
        return AutomationWebhook::where('tenant_id', $tenantId)
            ->orderByDesc('updated_at')->get()->all();
    }

    public function createWebhook(array $data): AutomationWebhook
    {
        return AutomationWebhook::create($data);
    }

    public function updateWebhook(AutomationWebhook $webhook, array $data): AutomationWebhook
    {
        $webhook->update($data);
        return $webhook->fresh();
    }

    public function deleteWebhook(AutomationWebhook $webhook): void
    {
        $webhook->rules()->detach();
        $webhook->delete();
    }

    // ─── 规则引擎核心 ───

    /**
     * 根据事件触发规则
     */
    public function trigger(string $eventType, array $eventData, ?int $tenantId = null): array
    {
        $results = [];

        $rules = AutomationRule::where('status', 'active')
            ->where('trigger_type', 'event')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('priority')
            ->get();

        foreach ($rules as $rule) {
            $triggerConfig = $rule->trigger_config ?? [];
            $matched = false;

            // 检查事件类型匹配
            if (!empty($triggerConfig['event_type'])) {
                $pattern = $triggerConfig['event_type'];
                if (str_contains($pattern, '*')) {
                    $matched = fnmatch($pattern, $eventType);
                } else {
                    $matched = $pattern === $eventType;
                }
            }

            if (!$matched) continue;

            $result = $this->evaluateAndExecute($rule, $eventData, $eventType);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * 评估条件并执行
     */
    public function evaluateAndExecute(AutomationRule $rule, array $context = [], ?string $triggerSource = null): array
    {
        $startTime = microtime(true);

        // 速率限制检查
        if (!$this->checkRateLimit($rule)) {
            return ['rule_id' => $rule->id, 'status' => 'skipped', 'reason' => 'rate_limit'];
        }

        // 冷却检查
        if (!$this->checkCooldown($rule)) {
            return ['rule_id' => $rule->id, 'status' => 'skipped', 'reason' => 'cooldown'];
        }

        // 条件评估
        $conditionsPassed = $this->evaluateConditions($rule->conditions ?? [], $context, $rule->condition_logic ?? 'all');

        $execution = AutomationExecutionLog::create([
            'rule_id' => $rule->id,
            'trigger_source' => $triggerSource ?? ($context['_trigger'] ?? 'manual'),
            'trigger_data' => $context,
            'conditions_result' => [
                'passed' => $conditionsPassed,
                'conditions' => $rule->conditions,
            ],
            'status' => $conditionsPassed ? 'running' : 'skipped',
            'action_count' => $conditionsPassed ? count($rule->actions ?? []) : 0,
            'executed_at' => now(),
        ]);

        if (!$conditionsPassed) {
            $execution->update(['status' => 'skipped', 'execution_time_ms' => $this->elapsed($startTime)]);
            return ['rule_id' => $rule->id, 'status' => 'skipped', 'reason' => 'conditions_not_met'];
        }

        // 执行动作
        $actionResults = $this->executeActions($rule, $execution, $context);

        $duration = $this->elapsed($startTime);
        $failedCount = count(array_filter($actionResults, fn($a) => $a['status'] === 'failed'));

        $execution->update([
            'status' => $failedCount === 0 ? 'completed' : ($failedCount === count($actionResults) ? 'failed' : 'completed'),
            'successful_actions' => count($actionResults) - $failedCount,
            'failed_actions' => $failedCount,
            'execution_time_ms' => $duration,
        ]);

        // 更新规则统计
        $rule->increment('execution_count');
        if ($failedCount === 0) {
            $rule->increment('success_count');
        } else {
            $rule->increment('failure_count');
        }
        $rule->update(['last_executed_at' => now()]);

        return [
            'rule_id' => $rule->id,
            'status' => $execution->status,
            'execution_id' => $execution->id,
            'duration_ms' => $duration,
            'actions' => $actionResults,
        ];
    }

    /**
     * 执行规则的所有动作
     */
    protected function executeActions(AutomationRule $rule, AutomationExecutionLog $execution, array $context): array
    {
        $actions = $rule->actions ?? [];
        $results = [];
        $executionMode = $rule->action_execution ?? 'sequential';

        foreach ($actions as $index => $action) {
            $type = $action['type'] ?? 'unknown';
            $config = $action['config'] ?? [];
            $actionStart = microtime(true);

            $actionLog = AutomationActionLog::create([
                'execution_id' => $execution->id,
                'rule_id' => $rule->id,
                'action_index' => $index,
                'action_type' => $type,
                'action_config' => $action,
                'input_data' => $context,
                'status' => 'running',
            ]);

            try {
                $output = $this->dispatchAction($type, $config, $context, $rule);

                $actionLog->update([
                    'status' => 'completed',
                    'output_data' => $output,
                    'duration_ms' => $this->elapsed($actionStart),
                ]);

                $results[] = ['index' => $index, 'type' => $type, 'status' => 'completed'];

                if ($executionMode === 'first_success') {
                    break;
                }
            } catch (\Exception $e) {
                $actionLog->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'duration_ms' => $this->elapsed($actionStart),
                ]);

                $results[] = ['index' => $index, 'type' => $type, 'status' => 'failed', 'error' => $e->getMessage()];

                if ($executionMode === 'sequential') {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * 分发执行单个动作
     */
    protected function dispatchAction(string $type, array $config, array $context, AutomationRule $rule): ?array
    {
        return match ($type) {
            'webhook' => $this->actionWebhook($config, $context),
            'send_email' => $this->actionSendEmail($config, $context, $rule),
            'update_license' => $this->actionUpdateLicense($config, $context, $rule),
            'update_subscription' => $this->actionUpdateSubscription($config, $context, $rule),
            'create_log' => $this->actionCreateLog($config, $context, $rule),
            'notify_admin' => $this->actionNotifyAdmin($config, $context, $rule),
            'suspend_tenant' => $this->actionSuspendTenant($config, $context, $rule),
            'toggle_feature_flag' => $this->actionToggleFeatureFlag($config, $context, $rule),
            default => throw new \InvalidArgumentException("不支持的动作类型: {$type}"),
        };
    }

    // ─── 内置动作 ───

    /**
     * 可用动作类型定义（给前端选择）
     */
    public function getAvailableActions(): array
    {
        return [
            'webhook' => ['label' => '发送 Webhook', 'fields' => ['webhook_id' => '关联 Webhook']],
            'send_email' => ['label' => '发送邮件', 'fields' => ['template' => '邮件模板', 'to' => '收件人', 'subject' => '主题']],
            'update_license' => ['label' => '更新 License', 'fields' => ['action' => '操作(expire/suspend/activate)', 'license_id' => 'License ID']],
            'update_subscription' => ['label' => '更新订阅', 'fields' => ['action' => '操作(cancel/pause/resume)', 'subscription_id' => '订阅 ID']],
            'create_log' => ['label' => '创建审计日志', 'fields' => ['type' => '日志类型', 'action' => '动作', 'description' => '描述']],
            'notify_admin' => ['label' => '通知管理员', 'fields' => ['message' => '通知内容']],
            'suspend_tenant' => ['label' => '暂停租户', 'fields' => ['reason' => '原因']],
            'toggle_feature_flag' => ['label' => '切换功能开关', 'fields' => ['flag' => '开关名称', 'value' => '值']],
        ];
    }

    protected function actionWebhook(array $config, array $context): ?array
    {
        $webhookId = $config['webhook_id'] ?? null;
        if (!$webhookId) throw new \RuntimeException('未指定 Webhook');

        $webhook = AutomationWebhook::find($webhookId);
        if (!$webhook) throw new \RuntimeException("Webhook {$webhookId} 不存在");

        $payload = array_merge(
            $webhook->body_template ?? [],
            ['triggered_at' => now()->toIso8601String(), 'context' => $context]
        );

        $response = Http::timeout(15)
            ->withHeaders($webhook->headers ?? [])
            ->{$webhook->method ?? 'post'}($webhook->url, $payload);

        $webhook->increment($response->successful() ? 'success_count' : 'failure_count');

        return ['status' => $response->status(), 'body' => substr($response->body(), 0, 1000)];
    }

    protected function actionSendEmail(array $config, array $context, AutomationRule $rule): ?array
    {
        $template = $config['template'] ?? 'default';
        $to = $config['to'] ?? 'admin';
        $subject = $this->interpolate($config['subject'] ?? 'Automation Notification', $context);

        Log::info("[AutomationRule:{$rule->id}] 发送邮件: {$subject} to {$to}");

        return ['sent' => true, 'to' => $to, 'subject' => $subject];
    }

    protected function actionUpdateLicense(array $config, array $context, AutomationRule $rule): ?array
    {
        $action = $config['action'] ?? 'expire';
        $licenseId = $config['license_id'] ?? $context['license_id'] ?? null;
        if (!$licenseId) throw new \RuntimeException('未指定 License');

        $license = \App\Models\License::find($licenseId);
        if (!$license) throw new \RuntimeException("License {$licenseId} 不存在");

        match ($action) {
            'expire' => $license->update(['status' => 'expired', 'expires_at' => now()]),
            'suspend' => $license->update(['status' => 'suspended']),
            'activate' => $license->update(['status' => 'active']),
        };

        return ['action' => $action, 'license_id' => $licenseId, 'status' => $license->status];
    }

    protected function actionUpdateSubscription(array $config, array $context, AutomationRule $rule): ?array
    {
        $action = $config['action'] ?? 'cancel';
        $subId = $config['subscription_id'] ?? $context['subscription_id'] ?? null;
        if (!$subId) throw new \RuntimeException('未指定订阅');

        $sub = \App\Models\Subscription::find($subId);
        if (!$sub) throw new \RuntimeException("订阅 {$subId} 不存在");

        match ($action) {
            'cancel' => $sub->update(['status' => 'canceled', 'canceled_at' => now()]),
            'pause' => $sub->update(['status' => 'paused']),
            'resume' => $sub->update(['status' => 'active']),
        };

        return ['action' => $action, 'subscription_id' => $subId];
    }

    protected function actionCreateLog(array $config, array $context, AutomationRule $rule): ?array
    {
        $logEntry = \App\Models\Log::create([
            'tenant_id' => $rule->tenant_id ?? $context['tenant_id'] ?? null,
            'type' => $config['type'] ?? 'automation',
            'action' => $config['action'] ?? 'rule.executed',
            'description' => $this->interpolate($config['description'] ?? "Rule {$rule->name} executed", $context),
            'payload' => ['rule_id' => $rule->id, 'context' => $context],
        ]);

        return ['log_id' => $logEntry->id];
    }

    protected function actionNotifyAdmin(array $config, array $context, AutomationRule $rule): ?array
    {
        $message = $this->interpolate($config['message'] ?? "Rule {$rule->name} triggered", $context);
        Log::warning("[AutomationRule:{$rule->id}] Admin通知: {$message}");

        return ['notified' => true, 'message' => $message];
    }

    protected function actionSuspendTenant(array $config, array $context, AutomationRule $rule): ?array
    {
        $tenantId = $rule->tenant_id ?? $context['tenant_id'] ?? null;
        if (!$tenantId) throw new \RuntimeException('未指定租户');

        $tenant = \App\Models\Tenant::find($tenantId);
        $tenant?->update(['status' => 'suspended']);

        return ['tenant_id' => $tenantId, 'status' => 'suspended'];
    }

    protected function actionToggleFeatureFlag(array $config, array $context, AutomationRule $rule): ?array
    {
        $flag = $config['flag'] ?? null;
        $value = $config['value'] ?? false;
        if (!$flag) throw new \RuntimeException('未指定功能开关');

        Log::info("[AutomationRule:{$rule->id}] 切换开关 {$flag} = " . ($value ? 'true' : 'false'));

        return ['flag' => $flag, 'value' => $value];
    }

    // ─── 条件评估 ───

    public function evaluateConditions(array $conditions, array $context, string $logic = 'all'): bool
    {
        if (empty($conditions)) return true;

        $results = [];
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'eq';
            $value = $condition['value'] ?? null;

            $actualValue = data_get($context, $field, $context[$field] ?? null);
            $results[] = $this->compare($actualValue, $operator, $value);
        }

        return $logic === 'all' ? !in_array(false, $results, true) : in_array(true, $results, true);
    }

    protected function compare($actual, string $operator, $expected): bool
    {
        return match ($operator) {
            'eq' => $actual == $expected,
            'neq' => $actual != $expected,
            'gt' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'gte' => is_numeric($actual) && is_numeric($expected) && $actual >= $expected,
            'lt' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            'lte' => is_numeric($actual) && is_numeric($expected) && $actual <= $expected,
            'contains' => is_string($actual) && str_contains($actual, (string) $expected),
            'starts_with' => is_string($actual) && str_starts_with($actual, (string) $expected),
            'ends_with' => is_string($actual) && str_ends_with($actual, (string) $expected),
            'in' => is_array($expected) && in_array($actual, $expected),
            'not_in' => is_array($expected) && !in_array($actual, $expected),
            'regex' => is_string($actual) && preg_match((string) $expected, $actual) === 1,
            'exists' => $actual !== null,
            'not_exists' => $actual === null,
            default => true,
        };
    }

    // ─── 速率限制与冷却 ───

    protected function checkRateLimit(AutomationRule $rule): bool
    {
        $perHour = $rule->max_executions_per_hour;
        $perDay = $rule->max_executions_per_day;

        if ($perHour > 0) {
            $count = AutomationExecutionLog::where('rule_id', $rule->id)
                ->where('created_at', '>=', now()->subHour())
                ->count();
            if ($count >= $perHour) return false;
        }

        if ($perDay > 0) {
            $count = AutomationExecutionLog::where('rule_id', $rule->id)
                ->where('created_at', '>=', now()->startOfDay())
                ->count();
            if ($count >= $perDay) return false;
        }

        return true;
    }

    protected function checkCooldown(AutomationRule $rule): bool
    {
        if ($rule->cooldown_minutes <= 0) return true;
        if (!$rule->last_executed_at) return true;
        return $rule->last_executed_at->diffInMinutes(now()) >= $rule->cooldown_minutes;
    }

    // ─── 仪表盘 ───

    public function getDashboard(int $tenantId): array
    {
        $total = AutomationRule::where('tenant_id', $tenantId)->count();
        $active = AutomationRule::where('tenant_id', $tenantId)->where('status', 'active')->count();
        $totalExec = AutomationExecutionLog::whereIn('rule_id',
            AutomationRule::where('tenant_id', $tenantId)->select('id')
        )->count();
        $recentExec = AutomationExecutionLog::whereIn('rule_id',
                AutomationRule::where('tenant_id', $tenantId)->select('id')
            )->where('created_at', '>=', now()->subDay())->count();
        $failed = AutomationExecutionLog::whereIn('rule_id',
                AutomationRule::where('tenant_id', $tenantId)->select('id')
            )->whereIn('status', ['failed', 'timeout'])->count();

        $byCategory = AutomationRule::where('tenant_id', $tenantId)
            ->selectRaw('category, COUNT(*) as cnt')
            ->groupBy('category')->get()->pluck('cnt', 'category')->toArray();

        return [
            'stats' => [
                'total_rules' => $total,
                'active_rules' => $active,
                'total_executions' => $totalExec,
                'recent_executions' => $recentExec,
                'failed_executions' => $failed,
            ],
            'by_category' => $byCategory,
        ];
    }

    // ─── 可用触发器 ───

    public function getAvailableTriggers(): array
    {
        return [
            'event' => [
                'label' => '事件触发',
                'description' => '当系统事件发生时触发',
                'events' => [
                    'license.*' => 'License 事件',
                    'license.created' => 'License 创建',
                    'license.expired' => 'License 过期',
                    'license.suspended' => 'License 暂停',
                    'license.activated' => 'License 激活',
                    'subscription.*' => '订阅事件',
                    'subscription.created' => '订阅创建',
                    'subscription.renewed' => '订阅续费',
                    'subscription.canceled' => '订阅取消',
                    'subscription.expired' => '订阅过期',
                    'invoice.*' => '发票事件',
                    'invoice.paid' => '发票已支付',
                    'invoice.overdue' => '发票逾期',
                    'customer.*' => '客户事件',
                    'customer.created' => '客户创建',
                    'device.*' => '设备事件',
                    'device.activated' => '设备激活',
                    'security.*' => '安全事件',
                    'security.breach' => '安全违规',
                ],
            ],
            'schedule' => [
                'label' => '定时触发',
                'description' => '按 Cron 表达式定时执行',
            ],
            'condition' => [
                'label' => '条件触发',
                'description' => '当条件满足时触发（如告警阈值）',
            ],
            'webhook' => [
                'label' => 'Webhook 触发',
                'description' => '通过外部 Webhook 调用触发',
            ],
        ];
    }

    // ─── 辅助 ───

    protected function interpolate(string $text, array $context): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function ($m) use ($context) {
            return $context[$m[1]] ?? $m[0];
        }, $text);
    }

    protected function elapsed(float $start): int
    {
        return (int) ((microtime(true) - $start) * 1000);
    }
}

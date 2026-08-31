<?php

namespace App\Services;

use App\Models\CustomerSmtpConfig;
use App\Models\SmtpDeliveryLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Swift_Mailer;
use Swift_SmtpTransport;

/**
 * 客户 SMTP 配置 & 多渠道降级服务 (M2-83 + M2-84)
 */
class CustomerSmtpService
{
    /**
     * 获取预设提供商列表
     */
    public function getProviders(): array
    {
        $providers = config('customer-smtp.providers', []);
        $list = [];
        foreach ($providers as $key => $cfg) {
            $list[$key] = [
                'key' => $key,
                'name' => $cfg['name'],
                'host' => $cfg['host'],
                'port' => $cfg['port'],
                'encryption' => $cfg['encryption'],
            ];
        }
        return $list;
    }

    /**
     * 创建 SMTP 配置
     */
    public function create(array $data): CustomerSmtpConfig
    {
        if (!empty($data['password'])) {
            $data['password'] = encrypt($data['password']);
        }

        $config = CustomerSmtpConfig::create($data);

        // 如果设为主 SMTP，取消其他主标记
        if ($config->is_primary) {
            CustomerSmtpConfig::where('tenant_id', $config->tenant_id)
                ->where('id', '!=', $config->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        return $config->fresh();
    }

    /**
     * 更新 SMTP 配置
     */
    public function update(CustomerSmtpConfig $config, array $data): CustomerSmtpConfig
    {
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = encrypt($data['password']);
        } else {
            unset($data['password']);
        }

        $config->update($data);

        if (!empty($data['is_primary'])) {
            CustomerSmtpConfig::where('tenant_id', $config->tenant_id)
                ->where('id', '!=', $config->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        return $config->fresh();
    }

    /**
     * 测试 SMTP 连接
     */
    public function testConnection(CustomerSmtpConfig $config): array
    {
        $password = $config->password ? decrypt($config->password) : '';

        try {
            $transport = new Swift_SmtpTransport(
                $config->host,
                $config->port,
                $config->encryption
            );
            $transport->setUsername($config->username);
            $transport->setPassword($password);
            $transport->setTimeout(10);

            $mailer = new Swift_Mailer($transport);
            $mailer->getTransport()->start();

            $config->update([
                'last_tested_at' => now(),
                'status' => 'active',
                'failure_count' => 0,
            ]);

            SmtpDeliveryLog::create([
                'smtp_config_id' => $config->id,
                'event_type' => 'test',
                'status' => 'success',
                'from_address' => $config->from_address,
            ]);

            return ['success' => true, 'message' => __('app.common.smtp_connection_success')];
        } catch (\Exception $e) {
            $config->increment('failure_count');
            $config->update(['last_failure_at' => now()]);

            SmtpDeliveryLog::create([
                'smtp_config_id' => $config->id,
                'event_type' => 'test',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => __('app.common.connection_failed', ['message' => $e->getMessage()])];
        }
    }

    /**
     * 发送邮件（带降级链）
     */
    public function send(string $to, string $subject, string $body, ?int $tenantId = null): array
    {
        // 1. 获取主 SMTP 配置
        $primaryConfig = CustomerSmtpConfig::byTenant($tenantId)
            ->primary()
            ->active()
            ->first();

        if ($primaryConfig) {
            $result = $this->sendViaConfig($primaryConfig, $to, $subject, $body);
            if ($result['success']) {
                return $result;
            }

            // 主 SMTP 失败，记录并尝试降级
            $this->logFailure($primaryConfig, $result['error'] ?? 'Unknown error');
        }

        // 2. 尝试备用 SMTP（按优先级排序）
        $backupConfigs = CustomerSmtpConfig::byTenant($tenantId)
            ->active()
            ->where('is_primary', false)
            ->orderByDesc('priority')
            ->get();

        foreach ($backupConfigs as $backup) {
            $result = $this->sendViaConfig($backup, $to, $subject, $body);
            if ($result['success']) {
                // 记录降级事件
                $this->logFailover($primaryConfig, $backup);
                return $result;
            }
            $this->logFailure($backup, $result['error'] ?? 'Unknown error');
        }

        // 3. 全部客户 SMTP 失败，降级到系统默认
        $result = $this->sendViaSystemDefault($to, $subject, $body);
        if ($result['success']) {
            $this->logGlobalFailover($to, $subject);
            return $result;
        }

        // 4. 系统默认也失败，记录告警
        $this->logCriticalAlert($to, $subject, $result['error'] ?? 'All SMTP failed');

        return ['success' => false, 'message' => __('app.common.all_smtp_send_failed_alert')];
    }

    /**
     * 通过指定配置发送
     */
    protected function sendViaConfig(CustomerSmtpConfig $config, string $to, string $subject, string $body): array
    {
        $password = $config->password ? decrypt($config->password) : '';

        try {
            $transport = new Swift_SmtpTransport(
                $config->host,
                $config->port,
                $config->encryption
            );
            $transport->setUsername($config->username);
            $transport->setPassword($password);

            $mailer = new Swift_Mailer($transport);

            $message = (new \Swift_Message($subject))
                ->setFrom([$config->from_address => $config->from_name])
                ->setTo([$to])
                ->setBody($body, 'text/html');

            $mailer->send($message);

            $config->update([
                'last_sent_at' => now(),
                'failure_count' => 0,
            ]);

            SmtpDeliveryLog::create([
                'smtp_config_id' => $config->id,
                'event_type' => 'send',
                'status' => 'success',
                'from_address' => $config->from_address,
                'to_address' => $to,
                'subject' => $subject,
            ]);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 通过系统默认 SMTP 发送
     */
    protected function sendViaSystemDefault(string $to, string $subject, string $body): array
    {
        try {
            $default = config('customer-smtp.system_default', []);

            $transport = new Swift_SmtpTransport(
                $default['host'],
                $default['port'],
                $default['encryption'] ?? null
            );
            if (!empty($default['username'])) {
                $transport->setUsername($default['username']);
                $transport->setPassword($default['password'] ?? '');
            }

            $mailer = new Swift_Mailer($transport);
            $message = (new \Swift_Message($subject))
                ->setFrom([$default['from_address'] => $default['from_name']])
                ->setTo([$to])
                ->setBody($body, 'text/html');

            $mailer->send($message);

            SmtpDeliveryLog::create([
                'event_type' => 'send',
                'status' => 'success',
                'from_address' => $default['from_address'],
                'to_address' => $to,
                'subject' => $subject,
                'fallback_action' => 'use_system_default',
            ]);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 记录主 SMTP 失败
     */
    protected function logFailure(CustomerSmtpConfig $config, string $error): void
    {
        $failureCount = $config->increment('failure_count');
        $config->update(['last_failure_at' => now()]);

        // 失败超过阈值，标记为 inactive
        $threshold = config('customer-smtp.fallback.failure_threshold', 3);
        if ($failureCount >= $threshold) {
            $config->update(['status' => 'failed']);

            if ($config->is_primary) {
                // 尝试自动切换到最高优先级的备用
                $backup = CustomerSmtpConfig::byTenant($config->tenant_id)
                    ->active()
                    ->where('is_primary', false)
                    ->orderByDesc('priority')
                    ->first();

                if ($backup) {
                    $backup->update(['is_primary' => true]);
                    $this->logFailover($config, $backup);
                }
            }
        }

        SmtpDeliveryLog::create([
            'smtp_config_id' => $config->id,
            'event_type' => $config->is_primary ? 'failover' : 'send',
            'status' => 'failed',
            'from_address' => $config->from_address,
            'error_message' => $error,
            'failure_count' => $failureCount,
        ]);
    }

    /**
     * 记录降级事件
     */
    protected function logFailover(?CustomerSmtpConfig $from, CustomerSmtpConfig $to): void
    {
        SmtpDeliveryLog::create([
            'smtp_config_id' => $to->id,
            'event_type' => 'failover',
            'status' => 'success',
            'from_address' => $to->from_address,
            'fallback_action' => 'switch_to_backup',
            'error_message' => $from ? "主SMTP #{$from->id} 失败，切换到备用 #{$to->id}" : '切换到备用 SMTP',
        ]);

        Log::warning("SMTP failover: primary #{$from?->id} -> backup #{$to->id}");
    }

    /**
     * 记录全局限流降级
     */
    protected function logGlobalFailover(string $to, string $subject): void
    {
        SmtpDeliveryLog::create([
            'event_type' => 'failover',
            'status' => 'success',
            'to_address' => $to,
            'subject' => $subject,
            'fallback_action' => 'use_system_default',
        ]);

        Log::warning("SMTP global failover: all customer SMTP failed, using system default");
    }

    /**
     * 记录严重告警（所有 SMTP 都失败）
     */
    protected function logCriticalAlert(string $to, string $subject, string $error): void
    {
        SmtpDeliveryLog::create([
            'event_type' => 'alert',
            'status' => 'failed',
            'to_address' => $to,
            'subject' => $subject,
            'error_message' => $error,
            'fallback_action' => 'alert_sent',
        ]);

        Log::critical("ALL SMTP failed for email to {$to}. Subject: {$subject}. Error: {$error}");
    }

    /**
     * 检查并恢复失败的 SMTP 配置
     */
    public function checkAndRecover(): array
    {
        $recovered = [];
        $recoveryInterval = config('customer-smtp.fallback.recovery_interval', 30);

        $failedConfigs = CustomerSmtpConfig::where('status', 'failed')
            ->where('last_failure_at', '<=', now()->subMinutes($recoveryInterval))
            ->get();

        foreach ($failedConfigs as $config) {
            $result = $this->testConnection($config);
            if ($result['success']) {
                $config->update([
                    'status' => 'active',
                    'recovered_at' => now(),
                    'failure_count' => 0,
                ]);

                SmtpDeliveryLog::create([
                    'smtp_config_id' => $config->id,
                    'event_type' => 'recovery',
                    'status' => 'success',
                    'from_address' => $config->from_address,
                ]);

                $recovered[] = ['id' => $config->id, 'host' => $config->host];
            }
        }

        return $recovered;
    }

    /**
     * 获取仪表盘
     */
    public function getDashboard(?int $tenantId = null): array
    {
        $configs = CustomerSmtpConfig::byTenant($tenantId)->get();

        return [
            'stats' => [
                'total' => $configs->count(),
                'active' => $configs->where('status', 'active')->count(),
                'failed' => $configs->where('status', 'failed')->count(),
                'primary' => $configs->where('is_primary', true)->first()?->only(['id', 'host', 'provider', 'from_address']),
            ],
            'providers' => $this->getProviders(),
            'fallback_config' => config('customer-smtp.fallback'),
            'system_default' => config('customer-smtp.system_default'),
        ];
    }

    /**
     * 获取日志
     */
    public function getLogs(array $filters = []): array
    {
        $query = SmtpDeliveryLog::with('smtpConfig:id,host,provider,from_address');

        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        $total = $query->count();
        $items = $query->orderByDesc('created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 获取配置列表
     */
    public function getConfigs(?int $tenantId = null): array
    {
        return CustomerSmtpConfig::byTenant($tenantId)
            ->with(['logs' => function ($q) { $q->latest()->limit(5); }])
            ->orderByDesc('is_primary')
            ->orderByDesc('priority')
            ->get()
            ->toArray();
    }

    /**
     * 测试 SMTP 降级链
     */
    public function testFallbackChain(?int $tenantId): array
    {
        $results = [];
        $configs = CustomerSmtpConfig::byTenant($tenantId)
            ->orderByDesc('is_primary')
            ->orderByDesc('priority')
            ->get();

        if ($configs->isEmpty()) {
            return [
                'success' => false,
                'message' => '未配置任何 SMTP',
                'results' => [],
            ];
        }

        foreach ($configs as $config) {
            try {
                $start = microtime(true);
                $transport = new \Swift_SmtpTransport($config->host, $config->port, $config->encryption);
                $transport->setUsername($config->username);
                $transport->setPassword($config->password);
                $transport->setTimeout(5);

                $mailer = new \Swift_Mailer($transport);
                $mailer->getTransport()->start();

                $elapsed = round((microtime(true) - $start) * 1000);
                $transport->stop();

                $results[] = [
                    'id' => $config->id,
                    'host' => $config->host,
                    'provider' => $config->provider,
                    'is_primary' => $config->is_primary,
                    'status' => 'success',
                    'latency_ms' => $elapsed,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'id' => $config->id,
                    'host' => $config->host,
                    'provider' => $config->provider,
                    'is_primary' => $config->is_primary,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $primaryOk = collect($results)->where('is_primary', true)->where('status', 'success')->isNotEmpty();
        $anyOk = collect($results)->where('status', 'success')->isNotEmpty();

        // 更新缓存状态
        if ($tenantId) {
            \Illuminate\Support\Facades\Cache::forever("smtp_fallback_status_{$tenantId}", [
                'primary_healthy' => $primaryOk,
                'backup_healthy' => $anyOk,
                'currently_using' => $primaryOk ? 'primary' : ($anyOk ? 'backup' : 'none'),
            ]);
        }

        return [
            'success' => $anyOk,
            'message' => $primaryOk ? '主 SMTP 正常' : ($anyOk ? '主 SMTP 异常，备用可用' : '所有 SMTP 均不可用'),
            'primary_healthy' => $primaryOk,
            'results' => $results,
        ];
    }
}

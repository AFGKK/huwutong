<?php

namespace App\Services;

use App\Models\DemoSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 交互式产品演示服务
 *
 * M3-70
 * 提供无需注册的在线体验：
 * - 30分钟限时会话
 * - 预置演示数据
 * - 引导式操作步骤
 * - 自动清理过期会话
 * - CTA注册引导
 */
class DemoService
{
    /**
     * 演示引导步骤定义
     */
    public const STEPS = [
        ['id' => 0, 'title' => '欢迎', 'page' => 'welcome'],
        ['id' => 1, 'title' => '查看仪表盘', 'page' => 'dashboard'],
        ['id' => 2, 'title' => '浏览产品', 'page' => 'products'],
        ['id' => 3, 'title' => '创建License', 'page' => 'create-license'],
        ['id' => 4, 'title' => '管理客户', 'page' => 'customers'],
        ['id' => 5, 'title' => '生成报告', 'page' => 'reports'],
        ['id' => 6, 'title' => '下一步', 'page' => 'next-steps'],
    ];

    /**
     * 演示数据种子
     */
    protected array $demoData = [
        'stats' => [
            'total_licenses' => 128,
            'active_licenses' => 95,
            'total_customers' => 42,
            'monthly_revenue' => 28500,
            'active_devices' => 312,
            'total_products' => 4,
        ],
        'revenue_trend' => [
            ['month' => '2026-01', 'new' => 12000, 'renewal' => 8000, 'churn' => 2000],
            ['month' => '2026-02', 'new' => 15000, 'renewal' => 9000, 'churn' => 1500],
            ['month' => '2026-03', 'new' => 18000, 'renewal' => 10000, 'churn' => 2500],
            ['month' => '2026-04', 'new' => 22000, 'renewal' => 12000, 'churn' => 1800],
            ['month' => '2026-05', 'new' => 25000, 'renewal' => 14000, 'churn' => 3000],
            ['month' => '2026-06', 'new' => 28500, 'renewal' => 15000, 'churn' => 2000],
        ],
        'products' => [
            ['id' => 1, 'name' => 'HWT License Core', 'slug' => 'hwt-core', 'version' => '3.2.0', 'licenses' => 58, 'revenue' => 14500, 'color' => '#409eff'],
            ['id' => 2, 'name' => 'HWT Enterprise', 'slug' => 'hwt-enterprise', 'version' => '2.1.0', 'licenses' => 32, 'revenue' => 9800, 'color' => '#67c23a'],
            ['id' => 3, 'name' => 'HWT Security Suite', 'slug' => 'hwt-security', 'version' => '1.8.0', 'licenses' => 22, 'revenue' => 2800, 'color' => '#e6a23c'],
            ['id' => 4, 'name' => 'HWT API Gateway', 'slug' => 'hwt-api', 'version' => '2.0.0', 'licenses' => 16, 'revenue' => 1400, 'color' => '#f56c6c'],
        ],
        'licenses' => [
            ['key' => 'DEMO-ENT-A1B2C3D4', 'type' => 'enterprise', 'status' => 'active', 'product' => 'HWT Enterprise', 'customer' => 'Acme Corp', 'expires' => '2027-06-01'],
            ['key' => 'DEMO-PRO-E5F6G7H8', 'type' => 'professional', 'status' => 'active', 'product' => 'HWT License Core', 'customer' => 'TechStart Inc', 'expires' => '2027-03-15'],
            ['key' => 'DEMO-STD-I9J0K1L2', 'type' => 'standard', 'status' => 'active', 'product' => 'HWT License Core', 'customer' => 'DataFlow Ltd', 'expires' => '2026-12-20'],
            ['key' => 'DEMO-BSC-M3N4O5P6', 'type' => 'basic', 'status' => 'expired', 'product' => 'HWT Security Suite', 'customer' => 'WebTech Co', 'expires' => '2026-01-10'],
            ['key' => 'DEMO-ENT-Q7R8S9T0', 'type' => 'enterprise', 'status' => 'active', 'product' => 'HWT Enterprise', 'customer' => 'GlobalSoft', 'expires' => '2027-08-22'],
        ],
        'customers' => [
            ['name' => 'Acme Corp', 'industry' => '科技', 'plan' => 'Enterprise', 'licenses' => 15, 'status' => 'active'],
            ['name' => 'TechStart Inc', 'industry' => '互联网', 'plan' => 'Professional', 'licenses' => 8, 'status' => 'active'],
            ['name' => 'DataFlow Ltd', 'industry' => '金融', 'plan' => 'Standard', 'licenses' => 5, 'status' => 'active'],
            ['name' => 'WebTech Co', 'industry' => '电商', 'plan' => 'Basic', 'licenses' => 2, 'status' => 'expired'],
            ['name' => 'GlobalSoft', 'industry' => '软件', 'plan' => 'Enterprise', 'licenses' => 20, 'status' => 'active'],
            ['name' => 'CloudNine', 'industry' => '云计算', 'plan' => 'Professional', 'licenses' => 10, 'status' => 'active'],
        ],
        'activities' => [
            ['action' => 'License激活', 'detail' => 'DEMO-ENT-A1B2C3D4 在 Windows Server 2022 上激活', 'time' => '2 分钟前'],
            ['action' => '新客户', 'detail' => 'CloudNine 注册并购买 Enterprise 套餐', 'time' => '15 分钟前'],
            ['action' => '设备注册', 'detail' => '新设备 (fingerprint: a1b2c3d4) 注册到 DEMO-ENT-Q7R8S9T0', 'time' => '1 小时前'],
            ['action' => 'License到期提醒', 'detail' => 'DEMO-STD-I9J0K1L2 将在 30 天后到期', 'time' => '3 小时前'],
            ['action' => '续费成功', 'detail' => 'TechStart Inc 自动续费 Professional 套餐', 'time' => '1 天前'],
        ],
        'chart_data' => [
            'activation_trend' => [85, 92, 88, 95, 102, 98, 105, 112, 108, 115, 120, 128],
            'revenue_monthly' => [18000, 21000, 23000, 25000, 28000, 28500],
            'device_platform' => ['Windows' => 45, 'macOS' => 25, 'Linux' => 18, 'iOS' => 7, 'Android' => 5],
        ],
    ];

    /**
     * 创建新演示会话
     */
    public function createSession(string $sessionId, ?string $ip = null, ?string $userAgent = null): DemoSession
    {
        // 检查当前session是否有激活的会话
        $existing = DemoSession::where('session_id', $sessionId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($existing) {
            $existing->update([
                'last_activity_at' => now(),
                'expires_at' => now()->addMinutes(30),
            ]);
            return $existing->fresh();
        }

        return DemoSession::createSession($sessionId, $ip, $userAgent);
    }

    /**
     * 验证并获取会话
     */
    public function getSession(string $token): ?DemoSession
    {
        $session = DemoSession::where('token', $token)->first();
        if (!$session || !$session->isValid()) {
            return null;
        }
        return $session;
    }

    /**
     * 获取演示数据（预置的种子数据）
     */
    public function getDemoData(DemoSession $session, string $type = 'all'): array
    {
        $session->update(['last_activity_at' => now()]);

        $data = match ($type) {
            'dashboard' => [
                'stats' => $this->demoData['stats'],
                'revenue_trend' => $this->demoData['revenue_trend'],
                'activities' => $this->demoData['activities'],
            ],
            'products' => $this->demoData['products'],
            'licenses' => $this->demoData['licenses'],
            'customers' => $this->demoData['customers'],
            'chart-data' => $this->demoData['chart_data'],
            default => $this->demoData,
        };

        return $data;
    }

    /**
     * 推进引导步骤
     */
    public function advanceStep(DemoSession $session, int $step): DemoSession
    {
        $step = max(0, min($step, count(self::STEPS) - 1));
        $session->update([
            'step' => $step,
            'current_page' => self::STEPS[$step]['page'] ?? null,
            'last_activity_at' => now(),
        ]);
        return $session->fresh();
    }

    /**
     * 记录完成的操作
     */
    public function completeAction(DemoSession $session, string $action): DemoSession
    {
        $actions = $session->completed_actions ?? [];
        if (!in_array($action, $actions)) {
            $actions[] = $action;
        }
        $session->update([
            'completed_actions' => $actions,
            'last_activity_at' => now(),
        ]);
        return $session->fresh();
    }

    /**
     * 获取当前引导步骤信息
     */
    public function getCurrentStep(DemoSession $session): array
    {
        $current = self::STEPS[$session->step] ?? self::STEPS[0];
        $total = count(self::STEPS);

        return [
            'current' => $current,
            'step' => $session->step,
            'total' => $total,
            'progress' => round(($session->step / max($total - 1, 1)) * 100),
            'completed_actions' => $session->completed_actions ?? [],
        ];
    }

    /**
     * 延长会话时间（CTA后延长体验）
     */
    public function extendSession(DemoSession $session, int $extraMinutes = 15): DemoSession
    {
        $session->update([
            'expires_at' => $session->expires_at->addMinutes($extraMinutes),
            'last_activity_at' => now(),
        ]);
        return $session->fresh();
    }

    /**
     * 完成演示（CTA注册后标记完成）
     */
    public function completeSession(DemoSession $session): DemoSession
    {
        $session->update([
            'status' => 'completed',
            'last_activity_at' => now(),
        ]);
        return $session->fresh();
    }

    /**
     * 心跳更新（保持会话活跃）
     */
    public function heartbeat(DemoSession $session): array
    {
        $session->update(['last_activity_at' => now()]);

        return [
            'remaining_seconds' => $session->remaining_seconds,
            'expiring_soon' => $session->isExpiringSoon(),
            'status' => $session->isValid() ? 'active' : 'expired',
            'current_step' => $this->getCurrentStep($session),
        ];
    }

    /**
     * 清理过期会话
     */
    public function cleanupExpiredSessions(): int
    {
        $count = DemoSession::where('expires_at', '<', now())
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        // 删除24小时前的已完成/过期会话
        $deleted = DemoSession::where('created_at', '<', now()->subDay())
            ->whereIn('status', ['completed', 'expired'])
            ->delete();

        Log::info("Demo cleanup: {$count} expired, {$deleted} deleted");
        return $count + $deleted;
    }

    /**
     * ─── 管理/分析功能 (M3-70 增强) ───
     */

    /**
     * 获取演示分析数据
     */
    public function getAnalytics(): array
    {
        $total = DemoSession::count();
        $active = DemoSession::where('status', 'active')->where('expires_at', '>', now())->count();
        $completed = DemoSession::where('status', 'completed')->count();
        $expired = DemoSession::where('status', 'expired')->count();

        $today = DemoSession::whereDate('created_at', today())->count();
        $thisWeek = DemoSession::where('created_at', '>=', now()->startOfWeek())->count();
        $thisMonth = DemoSession::where('created_at', '>=', now()->startOfMonth())->count();

        // 平均完成步骤
        $avgSteps = DemoSession::where('status', 'completed')
            ->avg('step') ?? 0;

        // 注册转化率 (完成演示后注册的比例)
        $withRegistration = DemoSession::where('status', 'completed')
            ->whereNotNull('completed_actions')
            ->get()
            ->filter(fn($s) => in_array('register', $s->completed_actions ?? []))
            ->count();

        $conversionRate = $completed > 0 ? round(($withRegistration / $completed) * 100, 1) : 0;

        // 每日趋势 (近7天)
        $dailyTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dailyTrend[] = [
                'date' => $date->format('Y-m-d'),
                'starts' => DemoSession::whereDate('created_at', $date)->count(),
                'completions' => DemoSession::whereDate('created_at', $date)->where('status', 'completed')->count(),
            ];
        }

        // 浏览器分布
        $browsers = DemoSession::selectRaw('SUBSTRING_INDEX(user_agent, " ", 1) as browser, COUNT(*) as count')
            ->whereNotNull('user_agent')
            ->groupBy('browser')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->toArray();

        return [
            'total' => $total,
            'active' => $active,
            'completed' => $completed,
            'expired' => $expired,
            'today' => $today,
            'this_week' => $thisWeek,
            'this_month' => $thisMonth,
            'avg_steps_completed' => round($avgSteps, 1),
            'conversion_rate' => $conversionRate,
            'registrations' => $withRegistration,
            'daily_trend' => $dailyTrend,
            'browsers' => $browsers,
        ];
    }

    /**
     * 获取配置
     */
    public function getConfig(): array
    {
        return [
            'session_duration_minutes' => DemoConfig::getConfig('session_duration_minutes', 30),
            'extend_minutes' => DemoConfig::getConfig('extend_minutes', 15),
            'enabled' => DemoConfig::getConfig('demo_enabled', true),
            'allowed_ips' => DemoConfig::getConfig('allowed_ips', []),
            'cta_title' => DemoConfig::getConfig('cta_title', '免费注册'),
            'cta_description' => DemoConfig::getConfig('cta_description', '创建您的账户，开始管理 License'),
            'seed_data_version' => DemoConfig::getConfig('seed_data_version', '1.0'),
            'auto_cleanup_minutes' => DemoConfig::getConfig('auto_cleanup_minutes', 60),
        ];
    }

    /**
     * 更新配置
     */
    public function updateConfig(array $settings): array
    {
        foreach ($settings as $key => $value) {
            DemoConfig::setConfig($key, $value);
        }

        return $this->getConfig();
    }

    /**
     * 获取嵌入代码
     */
    public function getEmbedCode(): string
    {
        $url = config('app.url');
        return <<<HTML
<!-- HWT License 交互式产品演示 -->
<div id="hwt-demo-container"></div>
<script src="{$url}/demo/embed.js" data-mode="floating"></script>
<script>
  window.HWT_DEMO_CONFIG = {
    container: '#hwt-demo-container',
    mode: 'floating', // 'floating' | 'inline' | 'modal'
    position: 'bottom-right',
    buttonText: '在线体验',
    themeColor: '#409eff',
  };
</script>
HTML;
    }

    /**
     * ─── CTA 注册引导 (M3-70 增强) ───
     */

    /**
     * 从演示会话注册用户
     * 完成演示后 CTA 引导注册，创建真实用户并关联演示会话
     */
    public function registerFromDemo(DemoSession $session, array $userData): array
    {
        $name = $userData['name'] ?? '';
        $email = $userData['email'] ?? '';
        $company = $userData['company'] ?? '';
        $password = $userData['password'] ?? \Illuminate\Support\Str::random(12);

        if (empty($name) || empty($email)) {
            throw new \InvalidArgumentException('姓名和邮箱必填');
        }

        // 检查邮箱是否已注册
        $existing = \App\Models\User::where('email', $email)->first();
        if ($existing) {
            // 已存在的用户，直接关联演示会话
            $session->update([
                'completed_actions' => array_merge($session->completed_actions ?? [], ['register']),
                'status' => 'completed',
            ]);
            return [
                'new_user' => false,
                'message' => '欢迎回来，您已有关联账户',
                'user_id' => $existing->id,
            ];
        }

        // 创建新用户（Demo 注册 - 简化注册流程）
        $user = \App\Models\User::create([
            'name' => $name,
            'email' => $email,
            'company' => $company,
            'password' => bcrypt($password),
            'email_verified_at' => now(), // Demo 注册自动验证邮箱
        ]);

        // 生成默认租户
        $tenant = \App\Models\Tenant::create([
            'name' => $company ?: "{$name}的团队",
            'owner_id' => $user->id,
        ]);
        $user->update(['tenant_id' => $tenant->id]);

        // 关联演示会话
        $session->update([
            'completed_actions' => array_merge($session->completed_actions ?? [], ['register']),
            'status' => 'completed',
        ]);

        // 生成 Sanctum token
        $token = $user->createToken('demo-registration')->plainTextToken;

        return [
            'new_user' => true,
            'message' => '注册成功！',
            'user_id' => $user->id,
            'token' => $token,
            'password' => $password,
        ];
    }
}

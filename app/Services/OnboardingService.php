<?php

namespace App\Services;

use App\Models\QuickStartItem;
use App\Models\Tutorial;
use App\Models\User;
use App\Models\UserOnboardingProgress;
use App\Models\UserTutorialProgress;
use Illuminate\Support\Facades\DB;

/**
 * SaaS 自助 Onboarding 服务
 *
 * 提供：
 * - 多步骤注册向导
 * - 快速启动清单
 * - 交互式入门教程
 * - 用户进度追踪
 */
class OnboardingService
{
    // ─── 注册向导 ───

    /**
     * 获取或创建用户 onboarding 进度
     */
    public function getOrCreateProgress(User $user): UserOnboardingProgress
    {
        return UserOnboardingProgress::firstOrCreate(
            ['user_id' => $user->id],
            [
                'current_step' => 'welcome',
                'completed_steps' => [],
                'is_completed' => $user->onboarding_completed,
                'started_at' => now(),
            ]
        );
    }

    /**
     * 获取当前步骤信息
     */
    public function getCurrentStep(User $user): array
    {
        $progress = $this->getOrCreateProgress($user);

        $stepInfo = $this->getStepDefinition($progress->current_step);

        return [
            'current_step' => $progress->current_step,
            'completed_steps' => $progress->completed_steps ?? [],
            'is_completed' => $progress->is_completed,
            'progress_pct' => $this->calcProgress($progress),
            'step_info' => $stepInfo,
        ];
    }

    /**
     * 完成当前步骤
     */
    public function completeStep(User $user, string $step, array $data = []): array
    {
        $progress = $this->getOrCreateProgress($user);

        // 验证步骤是否有效
        if (!in_array($step, UserOnboardingProgress::STEPS)) {
            throw new \InvalidArgumentException("无效的步骤: {$step}");
        }

        // 处理各步骤数据
        $this->handleStepData($user, $step, $data);

        // 标记步骤完成
        $progress->completeStep($step);

        // 如果所有步骤完成，初始化快速启动清单
        if ($progress->is_completed) {
            QuickStartItem::initForUser($user->id, $user->tenant_id);
        }

        return $this->getCurrentStep($user);
    }

    /**
     * 跳过后勤（标记为已完成但不执行步骤逻辑）
     */
    public function skipOnboarding(User $user, ?string $reason = null): void
    {
        $progress = $this->getOrCreateProgress($user);
        $progress->update([
            'current_step' => 'complete',
            'completed_steps' => UserOnboardingProgress::STEPS,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $user->update([
            'onboarding_completed' => true,
            'onboarding_skipped_at' => now(),
            'onboarding_skip_reason' => $reason,
        ]);

        QuickStartItem::initForUser($user->id, $user->tenant_id);
    }

    /**
     * 获取步骤定义
     */
    public function getStepDefinition(string $step): array
    {
        $definitions = [
            'welcome' => [
                'title' => '欢迎使用 HWT License',
                'description' => '我们将在几分钟内帮您完成系统设置',
                'icon' => 'MagicStick',
                'fields' => [],
            ],
            'profile' => [
                'title' => '完善个人资料',
                'description' => '设置您的个人资料和偏好',
                'icon' => 'User',
                'fields' => ['name', 'phone'],
            ],
            'tenant' => [
                'title' => '创建团队',
                'description' => '创建一个团队或公司账户来管理您的 License',
                'icon' => 'OfficeBuilding',
                'fields' => ['tenant_name', 'tenant_logo'],
            ],
            'product' => [
                'title' => '添加产品',
                'description' => '添加您要授权管理的第一款产品',
                'icon' => 'Goods',
                'fields' => ['product_name', 'product_description'],
            ],
            'api_key' => [
                'title' => '生成 API 密钥',
                'description' => '创建 API 密钥用于集成您的系统',
                'icon' => 'Key',
                'fields' => ['key_name'],
            ],
            'complete' => [
                'title' => '设置完成！',
                'description' => '您已准备好开始使用 HWT License 系统',
                'icon' => 'CircleCheck',
                'fields' => [],
            ],
        ];

        return $definitions[$step] ?? $definitions['welcome'];
    }

    /**
     * 处理步骤提交的数据
     */
    protected function handleStepData(User $user, string $step, array $data): void
    {
        match ($step) {
            'profile' => $this->handleProfileStep($user, $data),
            'tenant' => $this->handleTenantStep($user, $data),
            'product' => $this->handleProductStep($user, $data),
            'api_key' => $this->handleApiKeyStep($user, $data),
            default => null,
        };
    }

    protected function handleProfileStep(User $user, array $data): void
    {
        $update = [];
        if (!empty($data['name'])) $update['name'] = $data['name'];
        if (!empty($data['phone'])) $update['phone'] = $data['phone'];
        if (!empty($update)) {
            $user->update($update);
        }
    }

    protected function handleTenantStep(User $user, array $data): void
    {
        if (empty($data['tenant_name'])) return;

        $tenant = \App\Models\Tenant::create([
            'name' => $data['tenant_name'],
            'status' => 'active',
        ]);

        $user->update(['tenant_id' => $tenant->id]);
    }

    protected function handleProductStep(User $user, array $data): void
    {
        if (empty($data['product_name']) || !$user->tenant_id) return;

        \App\Models\Product::create([
            'tenant_id' => $user->tenant_id,
            'name' => $data['product_name'],
            'description' => $data['product_description'] ?? '',
            'status' => 'active',
        ]);
    }

    protected function handleApiKeyStep(User $user, array $data): void
    {
        if (empty($data['key_name']) || !$user->tenant_id) return;

        $plainText = \Illuminate\Support\Str::random(32);
        \App\Models\ApiKey::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'name' => $data['key_name'],
            'key' => hash('sha256', $plainText),
            'key_prefix' => substr($plainText, 0, 8),
            'permissions' => ['*'],
            'is_active' => true,
        ]);
    }

    protected function calcProgress(UserOnboardingProgress $progress): int
    {
        $total = count(UserOnboardingProgress::STEPS) - 1; // exclude 'complete'
        $completed = count(array_filter($progress->completed_steps ?? [], fn($s) => $s !== 'complete'));
        if ($total === 0) return 100;
        return (int) round(($completed / $total) * 100);
    }

    // ─── 快速启动清单 ───

    /**
     * 获取用户的快速启动清单
     */
    public function getQuickStartItems(User $user): array
    {
        $items = QuickStartItem::where('user_id', $user->id)
            ->orderBy('sort_order')
            ->get()
            ->all();

        // 如果没有清单，初始化
        if (empty($items)) {
            $items = QuickStartItem::initForUser($user->id, $user->tenant_id);
        }

        $completed = count(array_filter($items, fn($i) => $i->is_completed));

        return [
            'items' => $items,
            'total' => count($items),
            'completed' => $completed,
            'progress_pct' => count($items) > 0 ? (int) round(($completed / count($items)) * 100) : 0,
        ];
    }

    /**
     * 标记快速启动项目为已完成
     */
    public function completeQuickStartItem(User $user, string $itemKey): QuickStartItem
    {
        $item = QuickStartItem::where('user_id', $user->id)
            ->where('item_key', $itemKey)
            ->firstOrFail();

        $item->markCompleted();

        // 检查是否所有项目已完成
        $total = QuickStartItem::where('user_id', $user->id)->count();
        $completed = QuickStartItem::where('user_id', $user->id)->where('is_completed', true)->count();

        if ($total > 0 && $completed === $total) {
            $user->update(['onboarding_completed' => true]);
        }

        return $item->fresh();
    }

    // ─── 入门教程 ───

    /**
     * 获取教程列表
     */
    public function getTutorials(): array
    {
        $tutorials = Tutorial::where('is_published', true)
            ->orderBy('sort_order')
            ->get()
            ->all();

        if (empty($tutorials)) {
            $this->seedDefaultTutorials();
            $tutorials = Tutorial::where('is_published', true)
                ->orderBy('sort_order')
                ->get()
                ->all();
        }

        return $tutorials;
    }

    /**
     * 种子默认教程
     */
    public function seedDefaultTutorials(): void
    {
        $defaults = [
            [
                'slug' => 'getting-started',
                'title' => '快速入门',
                'description' => '了解 HWT License 系统的基本概念和操作流程',
                'category' => 'getting_started',
                'sort_order' => 1,
                'steps' => [
                    ['title' => '什么是 License', 'content' => 'License（许可证）是您授权客户使用您的软件产品的凭证。HWT License 支持多种 License 类型：试用版、标准版、企业版等。', 'icon' => 'Reading'],
                    ['title' => '创建您的第一个 License', 'content' => '进入「License 管理」页面，点击「创建 License」，填写产品信息、客户信息、有效期等字段即可生成。', 'icon' => 'Key'],
                    ['title' => '激活与验证', 'content' => '客户拿到 License Key 后可以通过 SDK 或 API 进行激活和验证。系统会自动记录激活信息和设备指纹。', 'icon' => 'Link'],
                    ['title' => '监控与分析', 'content' => '通过仪表盘和报表功能，您可以实时监控 License 使用情况、激活地理分布、违规检测等信息。', 'icon' => 'DataBoard'],
                ],
            ],
            [
                'slug' => 'product-management',
                'title' => '产品管理',
                'description' => '学习如何管理您的产品、版本和定价方案',
                'category' => 'core_features',
                'sort_order' => 2,
                'steps' => [
                    ['title' => '添加产品', 'content' => '在「产品管理」中添加您要授权的软件产品。每个产品可以关联多个 License。', 'icon' => 'Goods'],
                    ['title' => '配置定价方案', 'content' => '为产品设置不同的定价方案，支持按 seat、按设备数、按时间等多种计费模式。', 'icon' => 'Coin'],
                    ['title' => '优惠券管理', 'content' => '创建优惠券和折扣活动，支持百分比折扣、固定金额减免、试用期延长等多种类型。', 'icon' => 'Ticket'],
                ],
            ],
            [
                'slug' => 'api-integration',
                'title' => 'API 集成',
                'description' => '了解如何通过 API 和 SDK 集成 HWT License 系统',
                'category' => 'integration',
                'sort_order' => 3,
                'steps' => [
                    ['title' => '获取 API 密钥', 'content' => '在「API 密钥」页面生成您的 API 密钥，用于在 SDK 和 API 调用中进行身份验证。', 'icon' => 'Key'],
                    ['title' => 'SDK 集成', 'content' => 'HWT License 提供 PHP、Node.js、Python、Java 等多种语言的 SDK，方便快速集成。', 'icon' => 'Connection'],
                    ['title' => 'Webhook 配置', 'content' => '通过 Webhook 接收 License 状态变更、激活、续费等事件的实时通知。', 'icon' => 'Promotion'],
                ],
            ],
            [
                'slug' => 'security-best-practices',
                'title' => '安全最佳实践',
                'description' => '保护您的 License 系统和客户数据',
                'category' => 'advanced',
                'sort_order' => 4,
                'steps' => [
                    ['title' => 'MFA 双因素认证', 'content' => '为管理员账户启用双因素认证，增加账户安全性。', 'icon' => 'Lock'],
                    ['title' => 'API 安全', 'content' => '所有 API 调用均使用 HMAC-SHA256 签名，确保请求的完整性和真实性。', 'icon' => 'Key'],
                    ['title' => '审计日志', 'content' => '系统自动记录所有关键操作到审计日志，并支持 Merkle 链验证，确保日志不可篡改。', 'icon' => 'Document'],
                ],
            ],
        ];

        foreach ($defaults as $tutorial) {
            Tutorial::firstOrCreate(
                ['slug' => $tutorial['slug']],
                $tutorial
            );
        }
    }

    /**
     * 获取单个教程详情
     */
    public function getTutorial(string $slug): ?Tutorial
    {
        return Tutorial::where('slug', $slug)->where('is_published', true)->first();
    }

    /**
     * 获取用户的教程进度
     */
    public function getUserTutorialProgress(User $user): array
    {
        $tutorials = $this->getTutorials();
        $result = [];

        foreach ($tutorials as $tutorial) {
            $progress = UserTutorialProgress::where('user_id', $user->id)
                ->where('tutorial_id', $tutorial->id)
                ->first();

            $result[] = [
                'tutorial' => $tutorial,
                'progress' => $progress ? [
                    'current_step' => $progress->current_step,
                    'is_completed' => $progress->is_completed,
                    'completed_at' => $progress->completed_at,
                ] : null,
            ];
        }

        return $result;
    }

    /**
     * 更新教程步骤进度
     */
    public function updateTutorialProgress(User $user, int $tutorialId, int $step): UserTutorialProgress
    {
        $tutorial = Tutorial::findOrFail($tutorialId);
        $totalSteps = count($tutorial->steps ?? []);
        $isComplete = $step >= $totalSteps - 1;

        return UserTutorialProgress::updateOrCreate(
            ['user_id' => $user->id, 'tutorial_id' => $tutorialId],
            [
                'current_step' => $step,
                'is_completed' => $isComplete,
                'completed_at' => $isComplete ? now() : null,
            ]
        );
    }

    // ─── 仪表盘 ───

    /**
     * 获取完整的 Onboarding 仪表盘数据
     */
    public function getDashboard(User $user): array
    {
        $onboarding = $this->getOrCreateProgress($user);
        $stepInfo = $this->getCurrentStep($user);
        $quickStart = $this->getQuickStartItems($user);
        $tutorials = $this->getUserTutorialProgress($user);

        $tutorialCompleted = count(array_filter($tutorials, fn($t) => $t['progress'] && $t['progress']['is_completed']));

        return [
            'onboarding' => $stepInfo,
            'quick_start' => $quickStart,
            'tutorials' => $tutorials,
            'user' => [
                'onboarding_completed' => $user->onboarding_completed,
                'onboarding_skipped_at' => $user->onboarding_skipped_at,
                'has_tenant' => $user->tenant_id !== null,
                'has_products' => $user->tenant_id ? \App\Models\Product::where('tenant_id', $user->tenant_id)->exists() : false,
                'has_api_keys' => $user->tenant_id ? \App\Models\ApiKey::where('tenant_id', $user->tenant_id)->exists() : false,
            ],
            'stats' => [
                'tutorials_total' => count($tutorials),
                'tutorials_completed' => $tutorialCompleted,
                'quick_start_total' => $quickStart['total'],
                'quick_start_completed' => $quickStart['completed'],
            ],
        ];
    }
}

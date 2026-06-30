<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickStartItem extends Model
{
    protected $table = 'quick_start_items';

    protected $fillable = [
        'user_id', 'tenant_id', 'item_key', 'title', 'description',
        'category', 'action_url', 'action_label', 'sort_order',
        'is_completed', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function markCompleted(): void
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }

    /**
     * 为指定用户初始化默认的快速启动清单
     */
    public static function initForUser(int $userId, ?int $tenantId = null): array
    {
        $defaults = [
            ['item_key' => 'explore_dashboard', 'title' => '浏览仪表盘', 'description' => '了解系统概览和关键指标', 'category' => 'getting_started', 'action_url' => '/dashboard', 'action_label' => '前往仪表盘', 'sort_order' => 1],
            ['item_key' => 'create_first_license', 'title' => '创建第一个 License', 'description' => '生成并配置您的第一个 License', 'category' => 'getting_started', 'action_url' => '/licenses', 'action_label' => '创建 License', 'sort_order' => 2],
            ['item_key' => 'setup_product', 'title' => '配置产品', 'description' => '添加和管理您的产品信息', 'category' => 'getting_started', 'action_url' => '/products', 'action_label' => '管理产品', 'sort_order' => 3],
            ['item_key' => 'generate_api_key', 'title' => '生成 API 密钥', 'description' => '创建 API 密钥用于系统集成', 'category' => 'getting_started', 'action_url' => '/api-keys', 'action_label' => '生成密钥', 'sort_order' => 4],
            ['item_key' => 'invite_team', 'title' => '邀请团队成员', 'description' => '添加团队成员协作管理', 'category' => 'getting_started', 'action_url' => '/rbac', 'action_label' => '管理成员', 'sort_order' => 5],
            ['item_key' => 'setup_notifications', 'title' => '配置通知', 'description' => '设置邮件和通知偏好', 'category' => 'advanced', 'action_url' => '/settings', 'action_label' => '通知设置', 'sort_order' => 6],
            ['item_key' => 'explore_analytics', 'title' => '探索分析功能', 'description' => '查看 License 分析和使用情况', 'category' => 'advanced', 'action_url' => '/license-analytics', 'action_label' => '查看分析', 'sort_order' => 7],
            ['item_key' => 'configure_billing', 'title' => '配置计费', 'description' => '设置计费方案和支付方式', 'category' => 'advanced', 'action_url' => '/billing', 'action_label' => '计费设置', 'sort_order' => 8],
        ];

        $created = [];
        foreach ($defaults as $item) {
            $created[] = self::firstOrCreate(
                ['user_id' => $userId, 'item_key' => $item['item_key']],
                array_merge($item, ['tenant_id' => $tenantId])
            );
        }

        return $created;
    }
}

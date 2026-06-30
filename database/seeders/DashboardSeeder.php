<?php

namespace Database\Seeders;

use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\DashboardWidgetTemplate;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DashboardSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Widget 模板 ───
        $templates = [
            // 通用
            ['type' => 'stat', 'name' => '系统统计概览', 'category' => 'general',
             'default_config' => null,
             'default_layout' => ['w' => 4, 'h' => 2],
             'default_visual_options' => ['refresh_interval' => 300, 'border' => true],
             'is_system' => true, 'sort_order' => 1],
            ['type' => 'metric', 'name' => '核心指标卡', 'category' => 'general',
             'default_config' => null,
             'default_layout' => ['w' => 3, 'h' => 2],
             'default_visual_options' => ['refresh_interval' => 300, 'border' => true],
             'is_system' => true, 'sort_order' => 2],

            // License
            ['type' => 'stat', 'name' => 'License 统计', 'category' => 'license',
             'default_config' => null,
             'default_layout' => ['w' => 3, 'h' => 2],
             'default_visual_options' => ['refresh_interval' => 600, 'border' => true],
             'is_system' => true, 'sort_order' => 10],
            ['type' => 'chart', 'name' => 'License 状态分布', 'category' => 'license',
             'default_config' => null,
             'default_layout' => ['w' => 4, 'h' => 3],
             'default_visual_options' => ['refresh_interval' => 600, 'border' => true],
             'is_system' => true, 'sort_order' => 11],
            ['type' => 'list', 'name' => '最近 License', 'category' => 'license',
             'default_config' => null,
             'default_layout' => ['w' => 4, 'h' => 3],
             'default_visual_options' => ['refresh_interval' => 300, 'border' => true],
             'is_system' => true, 'sort_order' => 12],
            ['type' => 'alert', 'name' => '即将过期 License', 'category' => 'license',
             'default_config' => null,
             'default_layout' => ['w' => 4, 'h' => 3],
             'default_visual_options' => ['refresh_interval' => 600, 'border' => true],
             'is_system' => true, 'sort_order' => 13],

            // 账单
            ['type' => 'chart', 'name' => '订阅统计', 'category' => 'billing',
             'default_config' => null,
             'default_layout' => ['w' => 4, 'h' => 3],
             'default_visual_options' => ['refresh_interval' => 600, 'border' => true],
             'is_system' => true, 'sort_order' => 20],
            ['type' => 'stat', 'name' => '订阅概览', 'category' => 'billing',
             'default_config' => null,
             'default_layout' => ['w' => 3, 'h' => 2],
             'default_visual_options' => ['refresh_interval' => 600, 'border' => true],
             'is_system' => true, 'sort_order' => 21],

            // 客户
            ['type' => 'metric', 'name' => '用户统计', 'category' => 'customer',
             'default_config' => null,
             'default_layout' => ['w' => 3, 'h' => 2],
             'default_visual_options' => ['refresh_interval' => 600, 'border' => true],
             'is_system' => true, 'sort_order' => 30],
            ['type' => 'list', 'name' => '最新工单', 'category' => 'customer',
             'default_config' => null,
             'default_layout' => ['w' => 4, 'h' => 3],
             'default_visual_options' => ['refresh_interval' => 300, 'border' => true],
             'is_system' => true, 'sort_order' => 31],

            // 安全
            ['type' => 'chart', 'name' => '审计日志趋势', 'category' => 'security',
             'default_config' => null,
             'default_layout' => ['w' => 6, 'h' => 3],
             'default_visual_options' => ['refresh_interval' => 1200, 'border' => true],
             'is_system' => true, 'sort_order' => 40],

            // 系统
            ['type' => 'table', 'name' => '系统数据表', 'category' => 'system',
             'default_config' => null,
             'default_layout' => ['w' => 6, 'h' => 3],
             'default_visual_options' => ['refresh_interval' => 600, 'border' => true],
             'is_system' => true, 'sort_order' => 50],
            ['type' => 'iframe', 'name' => '嵌入看板', 'category' => 'system',
             'default_config' => ['url' => 'https://example.com'],
             'default_layout' => ['w' => 6, 'h' => 4],
             'default_visual_options' => ['refresh_interval' => 0, 'border' => false],
             'is_system' => true, 'sort_order' => 51],
        ];

        foreach ($templates as $tpl) {
            DashboardWidgetTemplate::updateOrCreate(
                ['name' => $tpl['name'], 'type' => $tpl['type']],
                $tpl
            );
        }

        // ─── 默认仪表盘（仅在无仪表盘时创建） ───
        if (Dashboard::count() > 0) return;

        $dashboard = Dashboard::create([
            'user_id' => null,
            'tenant_id' => null,
            'name' => '运营总览',
            'description' => '系统默认仪表盘，展示核心运营指标',
            'layout_type' => 'grid',
            'columns' => 12,
            'is_default' => true,
            'is_shared' => true,
            'sort_order' => 0,
        ]);

        $defaultWidgets = [
            ['type' => 'stat', 'title' => '系统统计概览', 'layout' => ['w' => 4, 'h' => 2], 'data_source' => ['type' => 'stats'], 'sort_order' => 1],
            ['type' => 'metric', 'title' => 'License 统计', 'layout' => ['w' => 3, 'h' => 2], 'data_source' => ['type' => 'license_stats'], 'sort_order' => 2],
            ['type' => 'metric', 'title' => '订阅概览', 'layout' => ['w' => 3, 'h' => 2], 'data_source' => ['type' => 'subscription_stats'], 'sort_order' => 3],
            ['type' => 'metric', 'title' => '用户统计', 'layout' => ['w' => 2, 'h' => 2], 'data_source' => ['type' => 'user_stats'], 'sort_order' => 4],
            ['type' => 'chart', 'title' => 'License 状态分布', 'layout' => ['w' => 4, 'h' => 3], 'data_source' => ['type' => 'license_stats'], 'sort_order' => 5],
            ['type' => 'chart', 'title' => '审计日志趋势', 'layout' => ['w' => 4, 'h' => 3], 'data_source' => ['type' => 'audit_stats', 'days' => 14], 'sort_order' => 6],
            ['type' => 'alert', 'title' => '即将过期 License', 'layout' => ['w' => 4, 'h' => 3], 'data_source' => ['type' => 'recent_licenses', 'limit' => 10], 'sort_order' => 7],
            ['type' => 'list', 'title' => '最新工单', 'layout' => ['w' => 4, 'h' => 3], 'data_source' => ['type' => 'recent_tickets', 'limit' => 10], 'sort_order' => 8],
        ];

        foreach ($defaultWidgets as $idx => $w) {
            DashboardWidget::create([
                'dashboard_id' => $dashboard->id,
                'type' => $w['type'],
                'title' => $w['title'],
                'layout' => $w['layout'],
                'data_source' => $w['data_source'],
                'visual_options' => ['refresh_interval' => 300, 'border' => true],
                'sort_order' => $w['sort_order'],
                'is_visible' => true,
            ]);
        }

        // ─── 第二块仪表盘 ───
        $dashboard2 = Dashboard::create([
            'user_id' => null,
            'tenant_id' => null,
            'name' => '技术监控',
            'description' => '系统技术指标监控仪表盘',
            'layout_type' => 'grid',
            'columns' => 12,
            'is_default' => false,
            'is_shared' => true,
            'sort_order' => 1,
        ]);

        DashboardWidget::create([
            'dashboard_id' => $dashboard2->id,
            'type' => 'stat', 'title' => '系统统计', 'layout' => ['w' => 4, 'h' => 2],
            'data_source' => ['type' => 'stats'],
            'visual_options' => ['refresh_interval' => 600, 'border' => true],
            'sort_order' => 1, 'is_visible' => true,
        ]);

        DashboardWidget::create([
            'dashboard_id' => $dashboard2->id,
            'type' => 'chart', 'title' => '审计日志趋势', 'layout' => ['w' => 6, 'h' => 3],
            'data_source' => ['type' => 'audit_stats', 'days' => 30],
            'visual_options' => ['refresh_interval' => 1200, 'border' => true],
            'sort_order' => 2, 'is_visible' => true,
        ]);

        DashboardWidget::create([
            'dashboard_id' => $dashboard2->id,
            'type' => 'list', 'title' => '最新工单', 'layout' => ['w' => 4, 'h' => 3],
            'data_source' => ['type' => 'recent_tickets', 'limit' => 10],
            'visual_options' => ['refresh_interval' => 300, 'border' => true],
            'sort_order' => 3, 'is_visible' => true,
        ]);
    }
}

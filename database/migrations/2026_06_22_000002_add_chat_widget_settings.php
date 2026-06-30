<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * 添加客服浮窗和反馈按钮的位置配置项
     *
     * 这些配置会出现在后台「系统设置 → 客服设置」分组中，
     * 管理员可直接在页面选择位置。
     */
    public function up(): void
    {
        $defaults = [
            // ── AI 客服浮窗 ──
            [
                'group' => 'service',
                'key' => 'chat_widget_position',
                'value' => 'right',
                'type' => 'select',
                'options' => ['left', 'right'],
                'description' => 'AI 智能客服浮窗位置（左/右）',
                'is_public' => true,
            ],
            [
                'group' => 'service',
                'key' => 'chat_widget_bottom',
                'value' => '24',
                'type' => 'text',
                'description' => 'AI 智能客服浮窗距离底部距离（px）',
                'is_public' => true,
            ],
            [
                'group' => 'service',
                'key' => 'chat_widget_width',
                'value' => '440',
                'type' => 'text',
                'description' => 'AI 智能客服窗口宽度（px）',
                'is_public' => true,
            ],
            [
                'group' => 'service',
                'key' => 'chat_widget_height',
                'value' => '640',
                'type' => 'text',
                'description' => 'AI 智能客服窗口高度（px）',
                'is_public' => true,
            ],
            // ── 反馈按钮 ──
            [
                'group' => 'service',
                'key' => 'feedback_widget_position',
                'value' => 'right',
                'type' => 'select',
                'options' => ['left', 'right'],
                'description' => '反馈浮窗位置（左/右）',
                'is_public' => true,
            ],
            [
                'group' => 'service',
                'key' => 'feedback_widget_bottom',
                'value' => '80',
                'type' => 'text',
                'description' => '反馈浮窗距离底部距离（px）',
                'is_public' => true,
            ],
        ];

        foreach ($defaults as $item) {
            SiteSetting::firstOrCreate(
                ['key' => $item['key']],
                $item
            );
        }
    }

    public function down(): void
    {
        SiteSetting::whereIn('key', [
            'chat_widget_position',
            'chat_widget_bottom',
            'chat_widget_width',
            'chat_widget_height',
            'feedback_widget_position',
            'feedback_widget_bottom',
        ])->delete();
    }
};

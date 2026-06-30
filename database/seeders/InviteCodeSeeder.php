<?php

namespace Database\Seeders;

use App\Models\InviteChannel;
use App\Models\RegistrationPortalConfig;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class InviteCodeSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 渠道分组 ───
        $channels = [
            [
                'name' => '官网推广',
                'slug' => 'official-website',
                'description' => '官网主站推广渠道',
                'type' => 'promotional',
                'status' => 'active',
                'tags' => ['推广', '官网'],
                'is_public' => true,
            ],
            [
                'name' => '社交媒体',
                'slug' => 'social-media',
                'description' => '微信、微博、抖音等社交平台',
                'type' => 'social',
                'status' => 'active',
                'tags' => ['社媒'],
                'is_public' => true,
            ],
            [
                'name' => '合作伙伴',
                'slug' => 'partner-channel',
                'description' => '渠道合作伙伴专属邀请码',
                'type' => 'partner',
                'status' => 'active',
                'tags' => ['合作伙伴'],
                'is_public' => false,
            ],
            [
                'name' => '线下活动',
                'slug' => 'event-registration',
                'description' => '展会、沙龙等线下活动',
                'type' => 'event',
                'status' => 'active',
                'tags' => ['活动'],
                'is_public' => false,
            ],
            [
                'name' => '邮件营销',
                'slug' => 'email-marketing',
                'description' => 'EDM 邮件营销活动',
                'type' => 'marketing',
                'status' => 'inactive',
                'tags' => ['营销', '邮件'],
                'is_public' => false,
            ],
        ];

        foreach ($channels as $channel) {
            InviteChannel::updateOrCreate(
                ['slug' => $channel['slug']],
                array_merge($channel, [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ])
            );
        }

        // ─── 门户配置 ───
        $configs = [
            'portal_enabled' => ['value' => false],
            'portal_title' => ['value' => '创建您的账户'],
            'portal_subtitle' => ['value' => '请使用邀请码注册，开始体验'],
            'portal_brand_name' => ['value' => config('app.name')],
            'require_invite' => ['value' => true],
            'require_email_verify' => ['value' => false],
            'accept_terms' => ['value' => true],
            'terms_url' => ['value' => '/terms'],
            'privacy_url' => ['value' => '/privacy'],
            'portal_features' => ['value' => [
                ['icon' => 'Key', 'title' => '邀请码保护', 'desc' => '仅限受邀用户注册，保障安全'],
                ['icon' => 'Lock', 'title' => '数据加密', 'desc' => '端到端加密传输，保护隐私'],
                ['icon' => 'Bell', 'title' => '实时通知', 'desc' => '注册成功即时通知管理团队'],
            ]],
            'allowed_domains' => ['value' => []],
        ];

        foreach ($configs as $key => $value) {
            RegistrationPortalConfig::updateOrCreate(
                ['key' => $key],
                ['value' => $value['value']]
            );
        }

        $this->command->info('已创建 ' . count($channels) . ' 个渠道分组和 ' . count($configs) . ' 条门户配置');
    }
}

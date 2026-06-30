<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $providers = [
            ['group' => 'oauth', 'key' => 'oauth_wechat_enabled', 'value' => '0', 'type' => 'boolean', 'description' => '微信登录'],
            ['group' => 'oauth', 'key' => 'oauth_qq_enabled', 'value' => '0', 'type' => 'boolean', 'description' => 'QQ登录'],
            ['group' => 'oauth', 'key' => 'oauth_apple_enabled', 'value' => '0', 'type' => 'boolean', 'description' => 'Apple登录'],
            ['group' => 'oauth', 'key' => 'oauth_google_enabled', 'value' => '0', 'type' => 'boolean', 'description' => 'Google登录'],
            ['group' => 'oauth', 'key' => 'oauth_github_enabled', 'value' => '0', 'type' => 'boolean', 'description' => 'GitHub登录'],
        ];

        foreach ($providers as $p) {
            SiteSetting::firstOrCreate(
                ['key' => $p['key']],
                $p
            );
        }
    }

    public function down(): void
    {
        SiteSetting::where('group', 'oauth')->delete();
    }
};

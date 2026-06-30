<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    protected array $credentials = [
        // provider => [key_suffix => description]
        'wechat' => ['app_id' => '微信 AppID', 'app_secret' => '微信 AppSecret'],
        'qq' => ['app_id' => 'QQ AppID', 'app_key' => 'QQ AppKey'],
        'apple' => ['service_id' => 'Apple Service ID', 'key_id' => 'Apple Key ID', 'team_id' => 'Apple Team ID'],
        'google' => ['client_id' => 'Google Client ID', 'client_secret' => 'Google Client Secret'],
        'github' => ['client_id' => 'GitHub Client ID', 'client_secret' => 'GitHub Client Secret'],
    ];

    public function up(): void
    {
        foreach ($this->credentials as $provider => $fields) {
            foreach ($fields as $suffix => $desc) {
                $key = "oauth_{$provider}_{$suffix}";
                SiteSetting::firstOrCreate(
                    ['key' => $key],
                    [
                        'group' => 'oauth',
                        'key' => $key,
                        'value' => '',
                        'type' => 'string',
                        'description' => $desc,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        foreach ($this->credentials as $provider => $fields) {
            foreach ($fields as $suffix => $desc) {
                SiteSetting::where('key', "oauth_{$provider}_{$suffix}")->delete();
            }
        }
    }
};

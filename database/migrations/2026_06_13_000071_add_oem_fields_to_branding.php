<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_branding_configs', function (Blueprint $table) {
            // OEM 增强字段
            $table->string('custom_domain', 255)->nullable()->after('social_links')->comment('绑定的自定义域名');
            $table->boolean('hide_platform_branding')->default(false)->after('custom_domain')->comment('隐藏平台品牌');
            $table->boolean('hide_powered_by')->default(false)->after('hide_platform_branding')->comment('隐藏Powered By');
            $table->json('email_branding')->nullable()->after('hide_powered_by')->comment('邮件品牌化 {from_name, logo_url, footer}');
            $table->string('login_page_logo_url', 500)->nullable()->after('login_bg_image')->comment('登录页Logo(可不同于品牌Logo)');
        });
    }

    public function down(): void
    {
        Schema::table('portal_branding_configs', function (Blueprint $table) {
            $table->dropColumn([
                'custom_domain',
                'hide_platform_branding',
                'hide_powered_by',
                'email_branding',
                'login_page_logo_url',
            ]);
        });
    }
};

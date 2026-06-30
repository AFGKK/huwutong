<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_chat_conversations', function (Blueprint $table) {
            $table->string('visitor_ip', 45)->nullable()->after('source')->comment('访客 IP 地址');
            $table->string('visitor_country', 100)->nullable()->after('visitor_ip')->comment('IP 归属国家');
            $table->string('visitor_region', 100)->nullable()->after('visitor_country')->comment('IP 归属省份/州');
            $table->string('visitor_city', 100)->nullable()->after('visitor_region')->comment('IP 归属城市');
            $table->string('visitor_isp', 100)->nullable()->after('visitor_city')->comment('IP 运营商');
            $table->string('visitor_browser', 100)->nullable()->after('visitor_isp')->comment('浏览器');
            $table->string('visitor_os', 100)->nullable()->after('visitor_browser')->comment('操作系统');
            $table->string('visitor_device', 100)->nullable()->after('visitor_os')->comment('设备类型');
            $table->string('referrer_url', 500)->nullable()->after('visitor_device')->comment('来源页面 URL');
            $table->string('referrer_domain', 200)->nullable()->after('referrer_url')->comment('来源域名');
        });
    }

    public function down(): void
    {
        Schema::table('live_chat_conversations', function (Blueprint $table) {
            $table->dropColumn([
                'visitor_ip', 'visitor_country', 'visitor_region', 'visitor_city',
                'visitor_isp', 'visitor_browser', 'visitor_os', 'visitor_device',
                'referrer_url', 'referrer_domain',
            ]);
        });
    }
};

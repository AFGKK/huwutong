<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 检查列是否已存在
        if (Schema::hasColumn('api_keys', 'permissions')) {
            return;
        }

        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('permissions', 20)->default('read-write')->after('secret')
                ->comment('权限级别: read-only, read-write, admin');
            $table->json('allowed_endpoints')->nullable()->after('permissions')
                ->comment('允许的端点列表，null=全部');
            $table->unsignedSmallInteger('rate_limit')->nullable()->after('allowed_endpoints')
                ->comment('每分钟请求上限');
            $table->unsignedInteger('usage_quota')->nullable()->after('rate_limit')
                ->comment('总请求配额');
            $table->unsignedInteger('usage_count')->default(0)->after('usage_quota')
                ->comment('已使用请求数');
            $table->ipAddress('allowed_ip')->nullable()->after('usage_count')
                ->comment('绑定 IP');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn(['permissions', 'allowed_endpoints', 'rate_limit', 'usage_quota', 'usage_count', 'allowed_ip']);
        });
    }
};

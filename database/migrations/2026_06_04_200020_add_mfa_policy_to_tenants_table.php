<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tenant MFA 策略和 IP 白名单
        // 此文件在 tenants 表创建后运行（由文件名排序保证）
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'mfa_policy')) {
                $table->string('mfa_policy', 20)->default('optional')->after('status')
                    ->comment('MFA 策略: optional/required_for_admin/required_for_all');
            }
            if (! Schema::hasColumn('tenants', 'allowed_ips')) {
                $table->json('allowed_ips')->nullable()->after('mfa_policy')
                    ->comment('管理后台 IP 白名单');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['mfa_policy', 'allowed_ips']);
        });
    }
};

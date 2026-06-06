<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // User MFA 字段
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'mfa_secret')) {
                $table->string('mfa_secret', 64)->nullable()->after('remember_token')->comment('TOTP 密钥');
            }
            if (! Schema::hasColumn('users', 'mfa_enabled')) {
                $table->boolean('mfa_enabled')->default(false)->after('mfa_secret')->comment('是否启用 MFA');
            }
            if (! Schema::hasColumn('users', 'mfa_recovery_codes')) {
                $table->json('mfa_recovery_codes')->nullable()->after('mfa_enabled')->comment('备用恢复码（哈希后存储）');
            }
            if (! Schema::hasColumn('users', 'mfa_recovery_used')) {
                $table->json('mfa_recovery_used')->nullable()->after('mfa_recovery_codes')->comment('已使用的恢复码索引');
            }
        });

        // MFA 设备表（记录用户绑定的 MFA 设备）
        Schema::create('mfa_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name', 100)->comment('设备名称');
            $table->string('secret', 64)->comment('TOTP 密钥');
            $table->string('type', 20)->default('totp')->comment('设备类型: totp/sms/email');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        // MFA 恢复码使用审计表
        Schema::create('mfa_recovery_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('mfa_device_id')->nullable();
            $table->string('action', 50)->comment('动作: generated/used/reset');
            $table->string('ip', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_recovery_audits');
        Schema::dropIfExists('mfa_devices');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'mfa_secret', 'mfa_enabled',
                'mfa_recovery_codes', 'mfa_recovery_used',
            ]);
        });
    }
};

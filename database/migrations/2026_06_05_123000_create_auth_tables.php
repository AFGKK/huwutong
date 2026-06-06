<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 邮箱验证码表
        Schema::create('email_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('token', 64);
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'token']);
        });

        // 记录每个用户最近成功登录的设备信息
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_fingerprint');
            $table->string('device_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('trusted_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_fingerprint']);
        });

        // 登录审计日志
        Schema::create('login_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('action')->comment('login/logout/failed/reset/bind');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('provider')->default('email')->comment('email/phone/wechat/google/github');
            $table->boolean('success')->default(true);
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        // 注册邀请码
        Schema::create('invite_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->nullableMorphs('created_by'); // 管理员或系统创建
            $table->integer('max_uses')->default(1);
            $table->integer('used_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('active')->comment('active/disabled/expired');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('code');
            $table->index('status');
        });

        // 隐私协议版本
        Schema::create('legal_consents', function (Blueprint $table) {
            $table->id();
            $table->string('type')->comment('privacy_policy/terms_of_service');
            $table->string('version', 20);
            $table->text('content');
            $table->boolean('is_current')->default(false);
            $table->timestamp('effective_at');
            $table->timestamps();
        });

        // 用户协议确认记录
        Schema::create('user_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_consent_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('consented_at');
            $table->timestamps();

            $table->unique(['user_id', 'legal_consent_id']);
        });

        // 账号注销申请
        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->string('status')->default('pending')->comment('pending/approved/cancelled/completed');
            $table->timestamp('cooling_until'); // 冷静期结束时间
            $table->timestamp('processed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verifications');
        Schema::dropIfExists('trusted_devices');
        Schema::dropIfExists('login_audits');
        Schema::dropIfExists('invite_codes');
        Schema::dropIfExists('legal_consents');
        Schema::dropIfExists('user_consents');
        Schema::dropIfExists('account_deletion_requests');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── IP 白名单 ───
        Schema::create('ip_whitelists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45);
            $table->string('label', 200)->nullable()->comment('标签/说明');
            $table->string('type', 20)->default('whitelist')->comment('whitelist/blacklist');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hit_count')->default(0)->comment('匹配次数');
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'is_active']);
            $table->index('ip_address');
        });

        // ─── 登录策略 ───
        Schema::create('login_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('policy_key', 80)->unique()->comment('策略标识: max_attempts/ lockout_duration/ password_policy/ mfa_required/ session_timeout/ geo_restriction');
            $table->string('value_type', 30)->default('string')->comment('string/integer/boolean/json');
            $table->text('value')->nullable()->comment('策略值');
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('policy_key');
        });

        // ─── 活跃会话 ───
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('session_id', 100)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('device_type', 50)->nullable()->comment('desktop/mobile/tablet/unknown');
            $table->string('browser', 100)->nullable();
            $table->string('os', 100)->nullable();
            $table->string('location', 200)->nullable()->comment('IP 地理位置');
            $table->boolean('is_current')->default(false);
            $table->boolean('is_mfa_verified')->default(false);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_current']);
            $table->index('last_activity_at');
        });

        // ─── 安全事件日志 ───
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event_type', 80)->comment('login_failed/ login_success/ logout/ session_expired/ ip_blocked/ mfa_challenge/ password_changed/ suspicious_activity/ geo_anomaly');
            $table->string('severity', 20)->default('info')->comment('info/warning/critical');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata')->nullable()->comment('事件附加数据');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event_type']);
            $table->index(['event_type', 'created_at']);
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('login_policies');
        Schema::dropIfExists('ip_whitelists');
    }
};

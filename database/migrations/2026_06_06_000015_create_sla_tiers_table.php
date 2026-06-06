<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SLA 等级定义
        Schema::create('sla_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('priority')->default(0)->comment('优先级（数字越高优先级越高）');
            $table->boolean('is_default')->default(false)->comment('是否默认等级');

            // API 并发限制
            $table->unsignedInteger('api_rate_limit')->default(60)->comment('API 每分钟最大请求数');
            $table->unsignedInteger('api_burst_limit')->default(100)->comment('API 突发最大请求数');
            $table->unsignedInteger('api_concurrent_limit')->default(10)->comment('API 最大并发数');

            // License 验证相关
            $table->unsignedInteger('verify_rate_limit')->default(120)->comment('License 验证每分钟最大次数');
            $table->unsignedSmallInteger('verify_timeout_seconds')->default(5)->comment('验证超时秒数');
            $table->unsignedInteger('max_active_licenses')->default(0)->comment('最大活跃 License 数（0=不限）');
            $table->unsignedInteger('max_devices_per_license')->default(0)->comment('每个 License 最大设备数（0=不限）');

            // 客服相关
            $table->unsignedSmallInteger('sla_response_hours')->default(48)->comment('首次响应 SLA（小时）');
            $table->unsignedSmallInteger('sla_resolution_hours')->default(120)->comment('解决 SLA（小时）');
            $table->boolean('support_priority_queue')->default(false)->comment('优先排队');
            $table->boolean('support_dedicated_manager')->default(false)->comment('专属客户经理');
            $table->boolean('support_phone')->default(false)->comment('电话支持');
            $table->boolean('support_24_7')->default(false)->comment('7x24 支持');

            // 审计与合规
            $table->unsignedSmallInteger('audit_retention_days')->default(365)->comment('审计日志保留天数');
            $table->boolean('require_mfa')->default(false)->comment('强制 MFA');
            $table->string('allowed_ip_ranges')->nullable()->comment('允许 IP 范围（CIDR,逗号分隔）');

            $table->timestamps();
        });

        // 客户 - SLA 等级关联（覆盖默认等级）
        Schema::create('customer_sla_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sla_tier_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('expires_at')->nullable()->comment('到期后自动恢复默认等级');
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_id']);
        });

        // SLA 事件审计日志
        Schema::create('sla_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sla_tier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 50)->comment('tier_assigned / tier_changed / tier_expired / limit_exceeded / sla_breached');
            $table->string('description')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_audit_events');
        Schema::dropIfExists('customer_sla_assignments');
        Schema::dropIfExists('sla_tiers');
    }
};

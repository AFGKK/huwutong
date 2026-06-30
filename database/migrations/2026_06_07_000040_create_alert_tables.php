<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 告警规则定义
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('metric_type'); // license_expiry, activation_burst, heartbeat_missed, payment_failed, apm_slow, sdk_deprecated, certificate_expiry, custom
            $table->string('condition_operator'); // gt, gte, lt, lte, eq, neq, pattern
            $table->decimal('threshold', 18, 4)->default(0);
            $table->integer('duration_minutes')->default(0)->comment('持续多久后触发（分钟）');
            $table->string('severity')->default('warning'); // critical, warning, info
            $table->json('channels')->comment('通知渠道: email, sms, webhook, slack, dingtalk');
            $table->json('webhook_urls')->nullable()->comment('自定义 Webhook URL');
            $table->string('slack_webhook')->nullable();
            $table->string('dingtalk_webhook')->nullable();
            $table->integer('cooldown_minutes')->default(60)->comment('冷却时间，防止重复告警');
            $table->integer('max_alert_per_day')->default(10)->comment('每日最大告警次数');
            $table->boolean('is_active')->default(true);
            $table->json('filters')->nullable()->comment('额外过滤条件: {tenant_id, product_id, plan}');
            $table->timestamp('last_triggered_at')->nullable();
            $table->unsignedInteger('daily_count')->default(0);
            $table->date('daily_count_date')->nullable();
            $table->timestamps();
        });

        // 告警事件（每次触发记录）
        Schema::create('alert_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_rule_id')->constrained()->cascadeOnDelete();
            $table->string('event_type')->index();
            $table->string('severity');
            $table->string('title');
            $table->text('message');
            $table->string('status')->default('firing'); // firing, acknowledged, resolved
            $table->json('context')->nullable()->comment('告警上下文数据');
            $table->json('channels_sent')->nullable()->comment('已发送的渠道');
            $table->string('source_type')->nullable()->comment('触发来源: license, subscription, heartbeat, apm, system');
            $table->unsignedBigInteger('source_id')->nullable()->comment('触发来源 ID');
            $table->timestamp('fired_at')->index();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['alert_rule_id', 'created_at']);
            $table->index(['status', 'severity', 'fired_at']);
            $table->index(['source_type', 'source_id']);
        });

        // 告警集成配置（Slack / DingTalk / Webhook）
        Schema::create('alert_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // slack, dingtalk, webhook, email_group
            $table->string('webhook_url');
            $table->text('description')->nullable();
            $table->json('config')->nullable()->comment('附加配置: {channel, mention, secret}');
            $table->string('severity_filter')->default('all')->comment('all, critical, warning, info');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_test_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_integrations');
        Schema::dropIfExists('alert_events');
        Schema::dropIfExists('alert_rules');
    }
};

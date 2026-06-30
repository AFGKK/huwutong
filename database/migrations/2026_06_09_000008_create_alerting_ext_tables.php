<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 通知渠道配置（扩展已有 alert_integrations） ───
        Schema::create('alert_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('type', 30)->comment('email/slack/webhook/sms/dingtalk/feishu/wechat/custom');
            $table->json('config')->comment('渠道配置 {webhook_url, api_key, recipients, phone_numbers}');
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'type']);
        });

        // ─── 告警规则 ↔ 通知渠道 多对多 ───
        Schema::create('alert_channel_rule', function (Blueprint $table) {
            $table->foreignId('alert_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alert_channel_id')->constrained()->cascadeOnDelete();
            $table->primary(['alert_rule_id', 'alert_channel_id']);
        });

        // ─── 升级策略 ───
        Schema::create('alert_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->foreignId('alert_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('escalation_level')->default(1)->comment('升级级别: 1/2/3');
            $table->unsignedSmallInteger('after_minutes')->default(30)->comment('告警持续N分钟后升级');
            $table->string('notify_type', 30)->comment('email/slack/sms/webhook');
            $table->json('notify_target')->comment('通知目标 {emails, slack_channel, phone_numbers, webhook_url}');
            $table->text('message_template')->nullable()->comment('自定义消息模板');
            $table->string('escalate_action', 50)->nullable()->comment('升级操作: notify_admin/create_ticket/run_webhook');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'alert_rule_id']);
        });

        // ─── 升级日志 ───
        Schema::create('alert_escalation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_event_id')->constrained('alert_events')->cascadeOnDelete();
            $table->foreignId('alert_escalation_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('escalation_level');
            $table->string('notify_type', 30);
            $table->string('status', 20)->default('pending')->comment('pending/sent/failed/skipped');
            $table->text('response')->nullable();
            $table->timestamps();

            $table->index(['alert_event_id', 'escalation_level']);
        });

        // ─── 通知发送记录 ───
        Schema::create('alert_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_event_id')->constrained('alert_events')->cascadeOnDelete();
            $table->foreignId('alert_channel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel_type', 30);
            $table->string('status', 20)->default('pending')->comment('pending/sent/failed');
            $table->text('response')->nullable()->comment('发送结果/错误信息');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['alert_event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_notification_logs');
        Schema::dropIfExists('alert_escalation_logs');
        Schema::dropIfExists('alert_escalations');
        Schema::dropIfExists('alert_channel_rule');
        Schema::dropIfExists('alert_channels');
    }
};

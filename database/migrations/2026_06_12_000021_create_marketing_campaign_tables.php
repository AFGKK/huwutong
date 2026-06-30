<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 营销活动表
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft')->comment('draft/active/paused/completed/cancelled');
            $table->string('type', 30)->comment('email/sms/in_app/multi_channel');

            // 目标受众
            $table->string('audience_type', 30)->default('all')->comment('all/segment/custom');
            $table->foreignId('segment_id')->nullable()->constrained('customer_segments')->nullOnDelete();
            $table->json('audience_filter')->nullable()->comment('自定义受众筛选条件');

            // 时间安排
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('timezone', 50)->default('UTC');

            // 渠道配置
            $table->json('channel_config')->nullable()->comment('各渠道的配置');

            // A/B 测试
            $table->boolean('is_ab_test')->default(false);
            $table->string('ab_test_metric', 30)->nullable()->comment('open_rate/click_rate/conversion_rate');
            $table->integer('ab_test_split')->default(50)->comment('A组百分比');
            $table->json('ab_test_variants')->nullable();

            // 统计
            $table->integer('target_count')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('delivered_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('clicked_count')->default(0);
            $table->integer('converted_count')->default(0);
            $table->integer('bounced_count')->default(0);
            $table->integer('unsubscribed_count')->default(0);

            // 预算
            $table->decimal('budget', 12, 2)->nullable();
            $table->decimal('cost_spent', 12, 2)->default(0);

            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'created_at']);
        });

        // 营销活动步骤
        Schema::create('marketing_campaign_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
            $table->integer('step_order');
            $table->string('action_type', 40)->comment('send_email/send_sms/send_notification/wait/condition/segment');
            $table->json('config')->nullable();
            $table->string('delay_type', 20)->default('immediate')->comment('immediate/delay/schedule');
            $table->integer('delay_minutes')->nullable();
            $table->json('conditions')->nullable()->comment('步骤执行条件');
            $table->timestamps();

            $table->index(['campaign_id', 'step_order']);
        });

        // 营销活动发送记录
        Schema::create('marketing_campaign_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
            $table->foreignId('step_id')->nullable()->constrained('marketing_campaign_steps')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20)->comment('email/sms/in_app');
            $table->string('recipient', 200);
            $table->string('status', 20)->default('pending')->comment('pending/sent/delivered/opened/clicked/converted/bounced/failed');
            $table->string('error_message', 500)->nullable();
            $table->string('message_id', 100)->nullable()->comment('外部服务消息ID');
            $table->string('ab_variant', 20)->nullable()->comment('A/B测试变体');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['campaign_id', 'customer_id']);
            $table->index(['campaign_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaign_logs');
        Schema::dropIfExists('marketing_campaign_steps');
        Schema::dropIfExists('marketing_campaigns');
    }
};

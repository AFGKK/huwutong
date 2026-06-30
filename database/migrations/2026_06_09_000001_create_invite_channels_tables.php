<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 渠道分组 ───
        Schema::create('invite_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->comment('渠道名称');
            $table->string('slug', 100)->unique()->comment('渠道标识');
            $table->text('description')->nullable();
            $table->string('type', 30)->default('promotional')->comment('类型: promotional/marketing/partner/event/social/internal');
            $table->string('status', 30)->default('active')->comment('active/inactive');
            $table->json('tags')->nullable();
            $table->boolean('is_public')->default(false)->comment('是否公开(自助注册展示)');
            $table->unsignedInteger('max_codes')->default(0)->comment('最多可生成邀请码数(0=不限)');
            $table->unsignedInteger('code_count')->default(0)->comment('已生成邀请码数');
            $table->unsignedInteger('registration_count')->default(0)->comment('注册转化数');
            $table->unsignedInteger('conversion_rate')->default(0)->comment('转化率(分比, 如 350=3.5%)');
            $table->json('landing_config')->nullable()->comment('落地页配置 {title, subtitle, banner, features}');
            $table->json('utm_defaults')->nullable()->comment('默认 UTM 参数 {source, medium, campaign}');
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('is_public');
        });

        // ─── 扩充 invite_codes 表字段 ───
        Schema::table('invite_codes', function (Blueprint $table) {
            $table->foreignId('channel_id')->nullable()->after('id')
                ->constrained('invite_channels')->nullOnDelete();
            $table->json('meta')->nullable()->after('remarks')
                ->comment('扩展元数据 {utm, tags, landing_page, extra}');
            $table->timestamp('last_used_at')->nullable()->after('used_count')
                ->comment('最近使用时间');
            $table->string('created_by_email', 255)->nullable()->after('created_by_id')
                ->comment('创建者邮箱(快照)');
        });

        // ─── 渠道每日统计 ───
        Schema::create('invite_channel_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('invite_channels')->cascadeOnDelete();
            $table->date('stat_date');
            $table->unsignedInteger('impressions')->default(0)->comment('展示次数');
            $table->unsignedInteger('clicks')->default(0)->comment('点击次数');
            $table->unsignedInteger('registrations')->default(0)->comment('注册数');
            $table->unsignedInteger('conversions')->default(0)->comment('转化数(付费)');
            $table->json('extra_data')->nullable()->comment('扩展数据');
            $table->timestamps();

            $table->unique(['channel_id', 'stat_date']);
            $table->index('stat_date');
        });

        // ─── 注册记录(增强追踪) ───
        Schema::create('registration_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invite_code', 50)->nullable()->index()->comment('使用的邀请码');
            $table->foreignId('channel_id')->nullable()->constrained('invite_channels')->nullOnDelete();
            $table->string('source', 50)->nullable()->comment('来源: invite/direct/social/oauth/trial');
            $table->string('referrer_url', 1000)->nullable()->comment('来源URL');
            $table->string('landing_page', 500)->nullable()->comment('落地页');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('utm_params')->nullable()->comment('UTM 参数');
            $table->boolean('converted')->default(false)->comment('是否转化为付费用户');
            $table->timestamp('converted_at')->nullable();
            $table->string('conversion_type', 50)->nullable()->comment('转化类型: subscription/purchase');
            $table->timestamps();

            $table->index(['channel_id', 'created_at']);
            $table->index(['source', 'created_at']);
            $table->index('converted');
        });

        // ─── 自助注册配置 ───
        Schema::create('registration_portal_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique()->comment('配置键');
            $table->json('value')->comment('配置值');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_tracking');
        Schema::dropIfExists('invite_channel_daily_stats');
        Schema::dropIfExists('registration_portal_configs');

        Schema::table('invite_codes', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
            $table->dropColumn(['channel_id', 'meta', 'last_used_at', 'created_by_email']);
            $table->dropIndex(['channel_id']);
        });

        Schema::dropIfExists('invite_channels');
    }
};

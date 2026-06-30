<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 联盟推广活动
        Schema::create('affiliate_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('活动名称');
            $table->string('slug')->unique()->comment('标识');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft')->comment('draft/active/paused/completed');
            $table->string('type', 30)->default('referral')->comment('referral/commission/reward/rebate');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('target_audience')->nullable()->comment('目标受众条件');
            $table->json('reward_rules')->nullable()->comment('奖励规则: 首次/续费/升级');
            $table->decimal('reward_first', 12, 2)->default(0)->comment('首次推荐奖励');
            $table->decimal('reward_renewal', 12, 2)->default(0)->comment('续费奖励比例 %');
            $table->decimal('reward_upgrade', 12, 2)->default(0)->comment('升级奖励比例 %');
            $table->decimal('budget_total', 14, 2)->default(0)->comment('总预算');
            $table->decimal('budget_used', 14, 2)->default(0)->comment('已使用预算');
            $table->integer('max_participants')->default(0)->comment('最大参与人数');
            $table->integer('participant_count')->default(0);
            $table->integer('conversion_count')->default(0);
            $table->json('terms')->nullable()->comment('活动条款');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index(['starts_at', 'ends_at']);
        });

        // 联盟推广素材
        Schema::create('affiliate_creatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('affiliate_campaigns')->cascadeOnDelete();
            $table->string('type', 30)->comment('banner/landing_page/link/coupon/qr_code');
            $table->string('name');
            $table->string('url')->nullable()->comment('素材链接/落地页');
            $table->text('content')->nullable()->comment('HTML/图文内容');
            $table->string('image_url')->nullable()->comment('图片URL');
            $table->json('utm_params')->nullable()->comment('UTM 参数');
            $table->integer('click_count')->default(0);
            $table->integer('conversion_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 推广关系链（多级）
        Schema::create('affiliate_tree', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('child_agent_id')->constrained('agents')->cascadeOnDelete();
            $table->integer('level')->default(1)->comment('层级: 1=直接, 2=间接');
            $table->decimal('rate', 5, 2)->default(0)->comment('分成比例 %');
            $table->string('status', 20)->default('active')->comment('active/inactive');
            $table->timestamp('attributed_at')->nullable();
            $table->timestamps();

            $table->unique(['parent_agent_id', 'child_agent_id']);
            $table->index('level');
        });

        // 联盟推广点击/转化追踪
        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('affiliate_campaigns')->nullOnDelete();
            $table->foreignId('creative_id')->nullable()->constrained('affiliate_creatives')->nullOnDelete();
            $table->string('referral_code')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('referrer_url')->nullable();
            $table->string('landing_url')->nullable();
            $table->json('utm_params')->nullable();
            $table->boolean('converted')->default(false);
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('commission_amount', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'created_at']);
            $table->index('referral_code');
            $table->index('converted');
        });

        // 给 agents 表增加多级推广字段
        Schema::table('agents', function (Blueprint $table) {
            $table->foreignId('parent_agent_id')->nullable()->after('user_id')->constrained('agents')->nullOnDelete();
            $table->decimal('multi_level_rate', 5, 2)->default(0)->after('parent_agent_id')->comment('上级分成比例 %');
            $table->integer('downline_count')->default(0)->after('multi_level_rate')->comment('下级代理数');
            $table->decimal('downline_earnings', 14, 2)->default(0)->after('downline_count')->comment('下级贡献收益');
            $table->string('referral_source', 30)->nullable()->after('notes')->comment('成为代理的来源');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropForeign(['parent_agent_id']);
            $table->dropColumn(['parent_agent_id', 'multi_level_rate', 'downline_count', 'downline_earnings', 'referral_source']);
        });
        Schema::dropIfExists('affiliate_clicks');
        Schema::dropIfExists('affiliate_tree');
        Schema::dropIfExists('affiliate_creatives');
        Schema::dropIfExists('affiliate_campaigns');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 代理/分销商 ───
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('agent_code', 20)->unique()->comment('代理商编号');
            $table->string('level', 30)->default('regular')->comment('regular/silver/gold/platinum');
            $table->string('status', 20)->default('active')->comment('active/suspended/terminated');
            $table->decimal('commission_rate', 5, 2)->nullable()->comment('自定义佣金比例(%)，空则使用等级默认');
            $table->decimal('total_earned', 12, 2)->default(0);
            $table->decimal('total_withdrawn', 12, 2)->default(0);
            $table->string('contact_name', 100)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('company', 200)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('level');
            $table->index('status');
        });

        // ─── 佣金计划（产品+等级→佣金比例） ───
        Schema::create('commission_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('计划名称');
            $table->string('slug', 100)->unique()->comment('唯一标识');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── 计划明细：产品×等级→佣金比例 ───
        Schema::create('commission_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_category', 50)->nullable()->comment('产品分类，优先级低于具体 product_id');
            $table->string('agent_level', 30)->default('regular');
            $table->decimal('commission_rate', 5, 2)->comment('佣金比例(%)');
            $table->enum('rate_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('fixed_amount', 12, 2)->nullable()->comment('固定金额（当 rate_type=fixed）');
            $table->unsignedInteger('tier_from_days')->nullable()->comment('计费周期起始天数');
            $table->unsignedInteger('tier_to_days')->nullable()->comment('计费周期结束天数');
            $table->unsignedTinyInteger('priority')->default(0)->comment('匹配优先级');
            $table->timestamps();

            $table->index(['commission_plan_id', 'product_id', 'agent_level'], 'cpi_cplan_product_level_idx');
            $table->index(['product_category', 'agent_level']);
        });

        // ─── 订阅的关联代理（谁推荐了这笔订阅） ───
        Schema::create('subscription_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commission_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('referral_code', 50)->nullable()->comment('使用的推广码');
            $table->string('attribution_source', 50)->nullable()->comment('归因来源：link/code/coupon');
            $table->timestamp('attributed_at')->useCurrent();
            $table->timestamps();

            $table->unique('subscription_id');
        });

        // ─── 佣金结算记录 ───
        Schema::create('commission_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period', 20)->comment('结算周期：2026-06');
            $table->string('status', 30)->default('pending')
                ->comment('pending/pending_release/released/cancelled/refunded');

            $table->decimal('invoice_amount', 12, 2)->comment('原始发票金额');
            $table->decimal('commission_rate', 5, 2)->comment('应用佣金比例(%)');
            $table->decimal('commission_amount', 12, 2)->comment('佣金金额');
            $table->string('rate_type', 20)->default('percentage');
            $table->string('settlement_type', 30)->default('subscription')
                ->comment('subscription/one_time/renewal/upgrade');

            $table->timestamp('settled_at')->nullable()->comment('结算日期');
            $table->timestamp('released_at')->nullable()->comment('释放日期（可提现）');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index(['agent_id', 'period']);
            $table->index(['status', 'released_at']);
        });

        // ─── 提现记录 ───
        Schema::create('commission_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('fee', 12, 2)->default(0)->comment('手续费');
            $table->decimal('net_amount', 12, 2)->comment('实际到账');
            $table->string('status', 30)->default('pending')
                ->comment('pending/processing/completed/failed/cancelled');
            $table->string('payout_method', 30)->default('bank_transfer')
                ->comment('bank_transfer/alipay/wechat/balance');
            $table->string('account_info', 500)->nullable()->comment('加密的收款账户信息');
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->string('transaction_id', 100)->nullable()->comment('外部交易ID');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index('status');
        });

        // ─── 推广链接/二维码 ───
        Schema::create('referral_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->unique()->comment('推广码');
            $table->string('name', 100)->nullable();
            $table->string('target_url', 500)->nullable()->comment('推广目标URL');
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->unsignedInteger('conversion_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('code');
            $table->index(['agent_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_links');
        Schema::dropIfExists('commission_payouts');
        Schema::dropIfExists('commission_settlements');
        Schema::dropIfExists('subscription_agents');
        Schema::dropIfExists('commission_plan_items');
        Schema::dropIfExists('commission_plans');
        Schema::dropIfExists('agents');
    }
};

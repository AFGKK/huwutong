<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 促销活动
        if (!Schema::hasTable('promotions')) {
            Schema::create('promotions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique()->comment('唯一标识');
                $table->string('type', 30)->comment('flash_sale|bulk_discount|bundle|x_for_y|free_gift|tiered');
                $table->text('description')->nullable();
                $table->string('status', 20)->default('draft')->comment('draft|active|paused|expired|cancelled');
                $table->json('rules')->nullable()->comment('促销规则配置');
                // 折扣配置
                $table->string('discount_type', 20)->nullable()->comment('percentage|fixed_amount|free');
                $table->decimal('discount_value', 10, 2)->nullable();
                $table->decimal('max_discount', 10, 2)->nullable();
                $table->decimal('min_order_amount', 10, 2)->nullable();
                // 适用性
                $table->json('applicable_plans')->nullable();
                $table->json('applicable_products')->nullable();
                $table->json('applicable_billing_periods')->nullable();
                // 预算/限制
                $table->integer('usage_limit')->nullable();
                $table->integer('usage_limit_per_customer')->nullable();
                $table->integer('usage_count')->default(0);
                $table->decimal('budget', 12, 2)->nullable()->comment('促销预算总额');
                $table->decimal('budget_spent', 12, 2)->default(0);
                // 时间
                $table->timestamp('starts_at');
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('published_at')->nullable();
                // 元数据
                $table->json('display_config')->nullable()->comment('显示配置：banner/位置/颜色/文案');
                $table->json('metadata')->nullable();
                $table->foreignId('created_by')->constrained('users');
                $table->timestamps();
                $table->softDeletes();

                $table->index('status');
                $table->index(['starts_at', 'ends_at']);
            });
        }

        // 2. 企业年框合同
        if (!Schema::hasTable('enterprise_contracts')) {
            Schema::create('enterprise_contracts', function (Blueprint $table) {
                $table->id();
                $table->string('contract_number')->unique()->comment('合同编号');
                $table->string('name');
                $table->foreignId('customer_id')->constrained();
                $table->string('status', 30)->default('draft')->comment('draft|pending_approval|active|expired|terminated');
                // 合同金额
                $table->decimal('total_value', 12, 2)->comment('合同总金额');
                $table->string('currency', 3)->default('CNY');
                $table->decimal('discount_rate', 5, 2)->default(0)->comment('折扣率 %');
                $table->decimal('negotiated_amount', 12, 2)->nullable()->comment('协商总价');
                // 期限
                $table->date('start_date');
                $table->date('end_date');
                $table->integer('billing_cycle_days')->default(365);
                // 授权内容
                $table->json('licensed_items')->comment('授权项：[{type: plan|product|seat|api_calls, id, name, quantity, unit_price}]');
                $table->json('terms')->nullable()->comment('合同条款');
                $table->json('special_terms')->nullable()->comment('特殊条款');
                // 审批
                $table->string('approval_status', 20)->default('pending');
                $table->text('approval_notes')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users');
                $table->timestamp('approved_at')->nullable();
                // 文件
                $table->string('signed_document_path')->nullable();
                $table->string('signed_document_name')->nullable();
                // 续签
                $table->boolean('auto_renew')->default(false);
                $table->integer('renewal_notice_days')->default(30);
                $table->foreignId('renewed_contract_id')->nullable()->constrained('enterprise_contracts');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users');
                $table->timestamps();
                $table->softDeletes();

                $table->index('status');
                $table->index(['start_date', 'end_date']);
            });
        }

        // 3. 促销使用记录
        if (!Schema::hasTable('promotion_redemptions')) {
            Schema::create('promotion_redemptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained();
                $table->foreignId('invoice_id')->nullable()->constrained();
                $table->string('promotion_type');
                $table->decimal('discount_amount', 10, 2);
                $table->string('currency', 3)->default('CNY');
                $table->json('context')->nullable()->comment('使用上下文：plan/order/items');
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();
            });
        }

        // 4. Coupon 表补充字段（关联促销活动）
        Schema::table('coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('coupons', 'promotion_id')) {
                $table->foreignId('promotion_id')->nullable()->constrained('promotions');
            }
            if (!Schema::hasColumn('coupons', 'is_stackable')) {
                $table->boolean('is_stackable')->default(false)->after('is_redeemable_with_other_coupons')->comment('可与其他优惠叠加');
            }
            if (!Schema::hasColumn('coupons', 'auto_apply')) {
                $table->boolean('auto_apply')->default(false)->comment('自动应用（无需用户手动输入）');
            }
            if (!Schema::hasColumn('coupons', 'priority')) {
                $table->integer('priority')->default(0)->comment('优先级：越大越优先');
            }
            if (!Schema::hasColumn('coupons', 'budget')) {
                $table->decimal('budget', 12, 2)->nullable()->comment('优惠券预算');
            }
            if (!Schema::hasColumn('coupons', 'budget_spent')) {
                $table->decimal('budget_spent', 12, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['promotion_id', 'is_stackable', 'auto_apply', 'priority', 'budget', 'budget_spent']);
        });
        Schema::dropIfExists('promotion_redemptions');
        Schema::dropIfExists('enterprise_contracts');
        Schema::dropIfExists('promotions');
    }
};

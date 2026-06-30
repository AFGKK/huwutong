<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 客户预付余额表
        if (!Schema::hasTable('prepaid_balances')) {
            Schema::create('prepaid_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->string('currency', 3)->default('CNY');
                $table->decimal('balance', 12, 2)->default(0)->comment('当前余额');
                $table->decimal('total_recharged', 12, 2)->default(0)->comment('累计充值');
                $table->decimal('total_consumed', 12, 2)->default(0)->comment('累计消费');
                $table->string('status', 20)->default('active')->comment('active/frozen/closed');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['customer_id', 'currency']);
                $table->index(['tenant_id', 'status']);
            });
        }

        // 客户信用额度表
        if (!Schema::hasTable('credit_limits')) {
            Schema::create('credit_limits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('credit_limit', 12, 2)->default(0)->comment('信用额度上限');
                $table->decimal('used_credit', 12, 2)->default(0)->comment('已使用额度');
                $table->unsignedSmallInteger('grace_days')->default(0)->comment('负余额宽限天数');
                $table->string('status', 20)->default('active')->comment('active/suspended/closed');
                $table->timestamp('last_assessment_at')->nullable()->comment('最近信用评估时间');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['customer_id']);
                $table->index(['tenant_id', 'status']);
            });
        }

        // 预付交易流水表
        if (!Schema::hasTable('prepaid_transactions')) {
            Schema::create('prepaid_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type', 30)->comment('recharge/consume/refund/adjust/credit_use/credit_repay');
                $table->decimal('amount', 12, 2)->comment('交易金额（正=入账，负=扣款）');
                $table->decimal('balance_before', 12, 2)->comment('交易前余额');
                $table->decimal('balance_after', 12, 2)->comment('交易后余额');
                $table->string('currency', 3)->default('CNY');
                $table->string('payment_method', 30)->nullable()->comment('充值方式：alipay/wechat/offline/admin');
                $table->string('gateway_transaction_id')->nullable()->comment('网关交易号');
                $table->string('status', 20)->default('completed')->comment('completed/pending/failed');
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['customer_id', 'type', 'created_at']);
                $table->index(['tenant_id', 'status']);
                $table->index('gateway_transaction_id');
            });
        }

        // 给 customers 表添加余额和信用相关字段
        if (!Schema::hasColumn('customers', 'prepaid_balance')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->decimal('prepaid_balance', 12, 2)->default(0)->after('status')
                    ->comment('预付余额（快照）');
                $table->decimal('credit_limit', 12, 2)->default(0)->after('prepaid_balance')
                    ->comment('信用额度');
                $table->decimal('credit_used', 12, 2)->default(0)->after('credit_limit')
                    ->comment('已用信用额度');
                $table->string('billing_method', 20)->default('invoice')
                    ->after('credit_used')
                    ->comment('结算方式: prepaid/credit/invoice');
            });
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['prepaid_balance', 'credit_limit', 'credit_used', 'billing_method']);
        });
        Schema::dropIfExists('prepaid_transactions');
        Schema::dropIfExists('credit_limits');
        Schema::dropIfExists('prepaid_balances');
    }
};

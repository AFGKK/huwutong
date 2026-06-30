<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 货币转换审计日志
        Schema::create('currency_conversion_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();

            $table->string('from_currency', 10);
            $table->string('to_currency', 10);
            $table->decimal('from_amount', 14, 2);
            $table->decimal('to_amount', 14, 2);
            $table->decimal('rate_used', 12, 6);
            $table->decimal('rate_markup', 6, 4)->default(0)->comment('汇率加点');
            $table->string('conversion_type', 30)->default('auto')->comment('auto/manual/checkout');
            $table->string('source', 50)->nullable()->comment('pricing/invoice/refund/subscription');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at'], 'ccl_tenant_created_idx');
            $table->index(['tenant_id', 'from_currency', 'to_currency'], 'ccl_tenant_fc_tc_idx');
        });

        // 跨境支付交易记录
        Schema::create('cross_border_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();

            $table->string('currency', 10)->comment('交易货币');
            $table->decimal('amount', 14, 2);
            $table->decimal('amount_cny', 14, 2)->comment('折合人民币');
            $table->decimal('exchange_rate', 12, 6)->comment('交易时汇率');
            $table->string('payment_gateway', 30)->nullable()->comment('stripe/alipay/mock');
            $table->string('gateway_transaction_id', 200)->nullable();
            $table->string('customer_country', 10)->nullable()->comment('客户所在国家');
            $table->string('merchant_country', 10)->default('CN')->comment('商户所在国家');
            $table->decimal('gateway_fee', 14, 4)->default(0)->comment('网关手续费(原币)');
            $table->decimal('gateway_fee_cny', 14, 4)->default(0)->comment('网关手续费(人民币)');
            $table->string('status', 30)->default('pending')->comment('pending/completed/failed/refunded');
            $table->string('transaction_type', 30)->default('payment')->comment('payment/refund/chargeback');
            $table->json('gateway_response')->nullable();
            $table->json('compliance_info')->nullable()->comment('合规检查信息');
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'currency'], 'cbp_tenant_currency_idx');
            $table->index(['tenant_id', 'status', 'created_at'], 'cbp_tenant_status_time_idx');
        });

        // 月度跨境支付报表快照
        Schema::create('cross_border_monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('report_month', 7)->comment('如 2026-06');
            $table->string('currency', 10);
            $table->decimal('total_revenue', 14, 2)->default(0)->comment('总收入(原币)');
            $table->decimal('total_revenue_cny', 14, 2)->default(0)->comment('总收入(人民币)');
            $table->decimal('total_refunds', 14, 2)->default(0);
            $table->decimal('total_fees', 14, 2)->default(0)->comment('总手续费(原币)');
            $table->decimal('total_fees_cny', 14, 2)->default(0)->comment('总手续费(人民币)');
            $table->decimal('net_revenue', 14, 2)->default(0)->comment('净收入(原币)');
            $table->integer('transaction_count')->default(0);
            $table->integer('customer_count')->default(0)->comment('去重客户数');
            $table->json('top_countries')->nullable()->comment('Top 5客户国家');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'report_month', 'currency'], 'cbmr_tenant_month_curr_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cross_border_monthly_reports');
        Schema::dropIfExists('cross_border_payments');
        Schema::dropIfExists('currency_conversion_logs');
    }
};

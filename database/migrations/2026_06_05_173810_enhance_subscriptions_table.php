<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // 计费字段
            $table->decimal('price', 12, 2)->default(0)->after('plan');
            $table->string('currency', 3)->default('CNY')->after('price');
            $table->string('billing_period')->default('monthly')->after('currency')
                ->comment('monthly/quarterly/semi_annually/yearly');
            $table->integer('grace_days')->default(7)->after('billing_period');
            $table->timestamp('grace_ends_at')->nullable()->after('trial_ends_at');
            $table->timestamp('canceled_at')->nullable()->after('grace_ends_at');
            $table->string('cancellation_reason')->nullable()->after('canceled_at');
            $table->json('payment_info')->nullable()->after('metadata')
                ->comment('支付方式信息（脱敏存储）');
            $table->timestamp('last_billed_at')->nullable()->after('payment_info');
            $table->timestamp('next_billing_at')->nullable()->after('last_billed_at');
            $table->integer('billing_cycles_completed')->default(0)->after('next_billing_at');
            $table->decimal('total_paid', 14, 2)->default(0)->after('billing_cycles_completed');
            $table->string('pricing_plan_slug')->nullable()->after('total_paid')
                ->comment('关联定价方案快照');

            $table->index(['tenant_id', 'next_billing_at']);
            $table->index(['status', 'next_billing_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('billing_reason')->nullable()->after('payment_method')
                ->comment('subscription_create/subscription_renew/manual/upgrade');
            $table->string('invoice_pdf_url')->nullable()->after('billing_reason');
            $table->text('notes')->nullable()->after('invoice_pdf_url');
            $table->timestamp('due_at')->nullable()->after('notes');
            $table->timestamp('refunded_at')->nullable()->after('paid_at');
            $table->index(['subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'billing_reason', 'invoice_pdf_url', 'notes', 'due_at', 'refunded_at',
            ]);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'price', 'currency', 'billing_period', 'grace_days', 'grace_ends_at',
                'canceled_at', 'cancellation_reason', 'payment_info', 'last_billed_at',
                'next_billing_at', 'billing_cycles_completed', 'total_paid', 'pricing_plan_slug',
            ]);
        });
    }
};

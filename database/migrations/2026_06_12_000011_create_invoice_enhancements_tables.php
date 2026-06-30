<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 发票模板
        Schema::create('invoice_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('code', 80)->unique();
            $table->boolean('is_default')->default(false);

            $table->json('header')->nullable()->comment('页眉：logo, company_name, address, phone, email');
            $table->json('footer')->nullable()->comment('页脚：bank_info, notes, terms');
            $table->string('color_scheme', 10)->default('blue')->comment('配色方案');
            $table->string('locale', 10)->default('zh_CN');
            $table->string('currency', 10)->default('CNY');

            $table->json('line_item_fields')->nullable()->comment('行项目字段配置');
            $table->json('show_fields')->nullable()->comment('显示项开关');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 账单对账
        Schema::create('invoice_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('reconciliation_type', 30)->default('auto')->comment('auto/manual/import');
            $table->string('status', 20)->default('pending')->comment('pending/matched/unmatched/resolved');
            $table->decimal('invoice_amount', 12, 2)->default(0);
            $table->decimal('actual_amount', 12, 2)->default(0)->comment('实际到账/对账金额');
            $table->decimal('difference', 12, 2)->default(0)->comment('差异金额');
            $table->string('currency', 10)->default('CNY');

            $table->string('payment_ref')->nullable()->comment('支付参考号/交易号');
            $table->timestamp('payment_date')->nullable();
            $table->text('notes')->nullable();
            $table->json('evidence')->nullable()->comment('对账凭证');

            $table->timestamp('matched_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'inv_rec_tenant_status_idx');
        });

        // 账单拆分
        Schema::create('invoice_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('original_invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('split_invoice_id')->constrained('invoices')->cascadeOnDelete();

            $table->decimal('amount', 12, 2)->comment('拆分金额');
            $table->string('reason', 200)->nullable();
            $table->string('status', 20)->default('completed')->comment('completed/reversed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_splits');
        Schema::dropIfExists('invoice_reconciliations');
        Schema::dropIfExists('invoice_templates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 税务合规报告
        Schema::create('tax_compliance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('report_type', 40)->comment('vat_return/gst_return/sales_tax/cross_border/liability_summary');
            $table->string('status', 20)->default('draft')->comment('draft/final/filed');
            $table->string('country', 2);
            $table->string('period', 7)->comment('申报周期, e.g. 2026-06');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->decimal('total_tax_collected', 14, 2)->default(0);
            $table->decimal('total_tax_payable', 14, 2)->default(0);
            $table->decimal('total_exempt_sales', 14, 2)->default(0);
            $table->decimal('total_reverse_charge', 14, 2)->default(0);
            $table->json('breakdown')->nullable()->comment('按税率分类的明细');
            $table->text('notes')->nullable();
            $table->timestamp('filed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'country', 'period', 'report_type'], 'tax_cr_tenant_ctry_period_type_uq');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'period']);
        });

        // 税务合规文档/税局通信
        Schema::create('tax_compliance_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 40)->comment('tax_return/filing_receipt/correspondence/certificate/audit_letter');
            $table->string('country', 2);
            $table->string('title', 200);
            $table->string('reference_number', 100)->nullable();
            $table->date('document_date');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('pending')->comment('pending/completed/overdue/archived');
            $table->string('file_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'country']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'document_date']);
        });

        // 税务自动化规则（特殊税率、减免规则等）
        Schema::create('tax_compliance_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('rule_type', 40)->comment('reduced_rate/exemption/threshold/special_zone');
            $table->string('country', 2)->nullable();
            $table->string('region_code', 10)->nullable();
            $table->string('condition_type', 40)->nullable()->comment('product_category/customer_type/amount_range');
            $table->string('condition_value', 100)->nullable();
            $table->decimal('rate_modifier', 6, 4)->nullable()->comment('税率调整值(如减免50%则填0.5)');
            $table->string('action', 40)->comment('apply_rate/exempt/reduce_rate/reverse_charge');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'rule_type']);
            $table->index(['tenant_id', 'country']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_compliance_rules');
        Schema::dropIfExists('tax_compliance_documents');
        Schema::dropIfExists('tax_compliance_reports');
    }
};

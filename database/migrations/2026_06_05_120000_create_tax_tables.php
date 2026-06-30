<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 税率表
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 2)->comment('ISO 3166-1 alpha-2');
            $table->string('region_code', 10)->nullable()->comment('州/省代码');
            $table->string('name')->comment('税种名称: VAT/GST/Sales Tax');
            $table->decimal('rate', 6, 4)->comment('税率（如 0.2000 = 20%）');
            $table->string('type')->default('vat')->comment('vat/gst/sales_tax');
            $table->string('category')->nullable()->comment('适用类别: standard/reduced/zero');
            $table->string('description')->nullable();
            $table->boolean('is_eu')->default(false)->comment('欧盟国家');
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->nullable()->comment('生效日期');
            $table->timestamp('effective_until')->nullable()->comment('失效日期');
            $table->timestamps();

            $table->unique(['country_code', 'region_code', 'type', 'category']);
            $table->index('country_code');
        });

        // 免税证书
        Schema::create('tax_exempt_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('certificate_type')->comment('vat_exempt/sales_tax_exempt/reseller');
            $table->string('certificate_number')->comment('证书编号');
            $table->string('issuing_country', 2)->comment('颁发国家');
            $table->text('reason')->nullable()->comment('免税理由');
            $table->string('status')->default('pending')->comment('pending/approved/rejected/expired');
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('document_file')->nullable()->comment('证明文件路径');
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // 发票税行
        Schema::create('invoice_tax_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('rate', 6, 4)->comment('税率');
            $table->decimal('taxable_amount', 12, 2)->comment('应税金额');
            $table->decimal('tax_amount', 12, 2)->comment('税额');
            $table->string('exempt_reason')->nullable()->comment('免税原因代码');
            $table->timestamps();
        });

        // 给 invoices 表增加税务相关字段
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('billing_country', 2)->nullable()->after('currency')->comment('账单国家');
            $table->string('billing_region', 10)->nullable()->comment('账单州/省');
            $table->string('billing_city')->nullable();
            $table->string('billing_zip')->nullable();
            $table->string('billing_address_line1')->nullable();
            $table->string('billing_address_line2')->nullable();
            $table->string('tax_type')->nullable()->comment('vat/gst/sales_tax/none');
            $table->decimal('tax_rate_applied', 6, 4)->nullable()->comment('适用税率');
            $table->decimal('tax_amount', 12, 2)->nullable()->comment('税额');
            $table->decimal('subtotal', 12, 2)->nullable()->comment('税前金额');
            $table->string('tax_exempt_certificate_id', 20)->nullable()->comment('免税证书编号');
            $table->string('tax_exempt_reason')->nullable();
            $table->string('tax_reporting_code')->nullable()->comment('税务申报代码 (EU: OSS/IOSS)');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'billing_country', 'billing_region', 'billing_city', 'billing_zip',
                'billing_address_line1', 'billing_address_line2',
                'tax_type', 'tax_rate_applied', 'tax_amount', 'subtotal',
                'tax_exempt_certificate_id', 'tax_exempt_reason', 'tax_reporting_code',
            ]);
        });
        Schema::dropIfExists('invoice_tax_lines');
        Schema::dropIfExists('tax_exempt_certificates');
        Schema::dropIfExists('tax_rates');
    }
};

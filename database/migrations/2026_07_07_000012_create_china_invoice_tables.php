<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 税控设备/税务UKey ──
        if (Schema::hasTable('china_tax_devices')) { return; }
        Schema::create('china_tax_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');                              // 设备名称
            $table->string('device_type');                       // ukey / tax_disk / cloud
            $table->string('tax_authority');                     // 税务机关: 国家税务总局/地方
            $table->string('taxpayer_id', 30);                   // 纳税人识别号(税号)
            $table->string('company_name');                      // 企业名称
            $table->string('registered_address')->nullable();    // 注册地址
            $table->string('phone')->nullable();                 // 电话
            $table->string('bank_name')->nullable();             // 开户行
            $table->string('bank_account')->nullable();          // 银行账号
            $table->text('certificate')->nullable();             // 税务证书(加密)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── 发票模板 ──
        if (Schema::hasTable('china_invoice_templates')) { return; }
        Schema::create('china_invoice_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');                              // 模板名称
            $table->string('invoice_type', 30);                  // vat_special / vat_normal / fiscal_bill / receipt
            $table->boolean('is_electronic')->default(true);     // 是否电子发票
            $table->string('title');                             // 发票抬头
            $table->string('tax_calculation', 30)->default('normal'); // normal / differential / simplified
            $table->json('line_item_defaults')->nullable();      // 默认行项目设置
            $table->json('metadata')->nullable();                // 自定义字段
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── 发票记录 ──
        if (Schema::hasTable('china_invoices')) { return; }
        Schema::create('china_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('china_invoice_templates')->nullOnDelete();
            $table->foreignId('tax_device_id')->nullable()->constrained('china_tax_devices')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_type', 30);                  // vat_special / vat_normal / fiscal_bill
            $table->string('invoice_code', 20);                  // 发票代码 (12位)
            $table->string('invoice_no', 20);                    // 发票号码 (8位)
            $table->string('tax_control_code', 100)->nullable(); // 税控码/校验码
            $table->string('qr_code_url')->nullable();           // 发票二维码
            $table->string('status', 30)->default('pending');    // pending / issued / voided / red_letter
            // 购买方信息
            $table->string('buyer_name');                        // 购买方名称
            $table->string('buyer_tax_id', 30)->nullable();      // 购买方税号
            $table->string('buyer_address')->nullable();
            $table->string('buyer_phone')->nullable();
            $table->string('buyer_bank')->nullable();
            $table->string('buyer_bank_account')->nullable();
            // 销售方信息
            $table->string('seller_name');
            $table->string('seller_tax_id', 30);
            $table->string('seller_address')->nullable();
            $table->string('seller_phone')->nullable();
            $table->string('seller_bank')->nullable();
            $table->string('seller_bank_account')->nullable();
            // 金额
            $table->decimal('amount', 14, 2);                    // 不含税金额
            $table->decimal('tax_rate', 5, 2)->default(13);      // 税率(%)
            $table->decimal('tax_amount', 14, 2);                // 税额
            $table->decimal('total_amount', 14, 2);              // 价税合计
            // 其他
            $table->string('drawer')->nullable();                 // 开票人
            $table->string('reviewer')->nullable();               // 复核人
            $table->string('payee')->nullable();                  // 收款人
            $table->text('remark')->nullable();
            $table->string('red_letter_source')->nullable();      // 红冲来源发票号
            $table->string('pdf_url')->nullable();                // 发票文件
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('invoice_code');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'issued_at']);
        });

        // ── 发票行项目 ──
        if (Schema::hasTable('china_invoice_items')) { return; }
        Schema::create('china_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('china_invoices')->cascadeOnDelete();
            $table->string('item_name');                          // 货物或应税劳务名称
            $table->string('specification')->nullable();          // 规格型号
            $table->string('unit')->nullable();                   // 单位
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 14, 6);                  // 单价
            $table->decimal('amount', 14, 2);                     // 金额
            $table->decimal('tax_rate', 5, 2)->default(13);       // 税率
            $table->decimal('tax_amount', 14, 2);                 // 税额
            $table->string('tax_code', 50)->nullable();           // 税收分类编码
            $table->string('tax_code_name')->nullable();          // 税收分类名称
            $table->string('is_discount', 10)->default('no');     // 是否折扣行
            $table->timestamps();
        });

        // ── 发票按月汇总申报 ──
        if (Schema::hasTable('china_tax_reports')) { return; }
        Schema::create('china_tax_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);                          // 所属期: 2026-07
            $table->string('report_type', 30)->default('vat');    // vat / surcharge / stamp
            $table->decimal('total_sales', 14, 2)->default(0);    // 销售额
            $table->decimal('total_tax', 14, 2)->default(0);      // 应纳税额
            $table->decimal('deductible_tax', 14, 2)->default(0); // 可抵扣税额
            $table->decimal('payable_tax', 14, 2)->default(0);    // 应缴税额
            $table->json('breakdown')->nullable();                 // 明细
            $table->string('status', 20)->default('draft');       // draft / submitted / approved
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'period', 'report_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('china_tax_reports');
        Schema::dropIfExists('china_invoice_items');
        Schema::dropIfExists('china_invoices');
        Schema::dropIfExists('china_invoice_templates');
        Schema::dropIfExists('china_tax_devices');
    }
};

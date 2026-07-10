<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 合规报告 ───
        if (Schema::hasTable('license_compliance_reports')) { return; }
        Schema::create('license_compliance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('type');              // full_inventory | activation_audit | compliance_summary | custom
            $table->string('format')->default('xlsx'); // xlsx | csv | pdf
            $table->string('status')->default('pending'); // pending | generating | completed | failed
            $table->json('filters')->nullable(); // 报告筛选条件
            $table->json('summary_data')->nullable(); // 摘要数据
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->integer('file_size')->nullable();
            $table->timestamp('report_period_start')->nullable();
            $table->timestamp('report_period_end')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_compliance_reports');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('compliance_ai_reports')) {
            return;
        }
        Schema::create('compliance_ai_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('framework', 30)->comment('gdpr/soc2/iso27001');
            $table->string('title', 300);
            $table->string('status', 30)->default('draft')->comment('draft/generating/completed/failed');
            $table->json('sections')->nullable()->comment('各章节AI生成内容');
            $table->json('evidence_summary')->nullable()->comment('证据项统计');
            $table->json('gap_analysis')->nullable()->comment('差距分析');
            $table->json('recommendations')->nullable()->comment('改进建议');
            $table->text('ai_prompt')->nullable()->comment('AI生成提示词');
            $table->text('ai_response')->nullable()->comment('AI原始响应');
            $table->string('file_path', 500)->nullable()->comment('PDF/HTML文件路径');
            $table->string('language', 10)->default('zh-CN');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('compliance_evidence_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_ai_report_id')->constrained('compliance_ai_reports')->cascadeOnDelete();
            $table->string('framework', 30);
            $table->string('section', 100)->comment('所属章节');
            $table->string('control_id', 50)->nullable()->comment('控制项编号');
            $table->string('title', 300);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('pending')->comment('compliant/partial/non_compliant/not_applicable');
            $table->text('evidence')->nullable()->comment('证据描述');
            $table->text('gap')->nullable()->comment('差距说明');
            $table->text('recommendation')->nullable()->comment('整改建议');
            $table->string('priority', 20)->default('medium')->comment('high/medium/low');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_evidence_items');
        Schema::dropIfExists('compliance_ai_reports');
    }
};

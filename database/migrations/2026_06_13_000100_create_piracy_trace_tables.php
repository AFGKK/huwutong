<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('piracy_scan_tasks')) {
            return;
        }
        Schema::create('piracy_scan_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 30)->comment('github/pastebin/darkweb/telegram/manual');
            $table->string('query', 500)->nullable()->comment('搜索关键词/URL');
            $table->string('status', 30)->default('pending')->comment('pending/running/completed/failed');
            $table->unsignedInteger('urls_found')->default(0);
            $table->unsignedInteger('matches_found')->default(0);
            $table->unsignedInteger('confirmed')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('result_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('piracy_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piracy_scan_task_id')->nullable()->constrained('piracy_scan_tasks')->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->string('license_key', 200)->nullable();
            $table->string('source', 30);
            $table->string('source_url', 1000)->comment('泄露位置URL');
            $table->text('snippet')->nullable()->comment('泄露代码片段');
            $table->string('screenshot_path', 500)->nullable()->comment('截图路径');
            $table->unsignedTinyInteger('confidence')->default(0)->comment('0-100');
            $table->string('confidence_level', 20)->default('low')->comment('low/medium/high/confirmed');
            $table->string('matched_pattern', 200)->nullable()->comment('匹配的正则/AI模式');
            $table->json('context')->nullable()->comment('发现上下文');
            $table->string('status', 30)->default('open')->comment('open/investigating/confirmed/false_positive/resolved');
            $table->string('assignee', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['license_id', 'status']);
            $table->index(['source', 'confidence']);
            $table->index('detected_at');
        });

        Schema::create('piracy_forensic_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piracy_evidence_id')->nullable()->constrained('piracy_evidence')->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 300);
            $table->string('report_type', 30)->default('incident')->comment('incident/evidence/summary');
            $table->json('evidence_items')->nullable();
            $table->text('analysis')->nullable()->comment('AI分析结论');
            $table->json('timeline')->nullable()->comment('泄露时间线');
            $table->json('affected_licenses')->nullable();
            $table->string('recommended_action', 500)->nullable();
            $table->string('file_path', 500)->nullable()->comment('PDF/HTML报告路径');
            $table->string('status', 30)->default('draft')->comment('draft/final/archived');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piracy_forensic_reports');
        Schema::dropIfExists('piracy_evidence');
        Schema::dropIfExists('piracy_scan_tasks');
    }
};

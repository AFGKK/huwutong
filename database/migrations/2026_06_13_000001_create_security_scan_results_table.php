<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * M2-112: OWASP ZAP 安全扫描结果表
     */
    public function up(): void
    {
        Schema::create('security_scan_results', function (Blueprint $table) {
            $table->id();
            $table->string('scan_type', 20)->default('full')->comment('扫描类型: baseline/full/api');
            $table->string('target_url')->comment('扫描目标 URL');
            $table->integer('high_count')->default(0)->comment('高危漏洞数');
            $table->integer('medium_count')->default(0)->comment('中危漏洞数');
            $table->integer('low_count')->default(0)->comment('低危漏洞数');
            $table->boolean('passed')->default(false)->comment('是否通过');
            $table->json('alerts')->nullable()->comment('告警详情');
            $table->string('report_file')->nullable()->comment('报告文件路径');
            $table->timestamp('executed_at')->nullable()->comment('执行时间');
            $table->timestamps();

            $table->index('scan_type');
            $table->index('passed');
            $table->index('executed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_scan_results');
    }
};

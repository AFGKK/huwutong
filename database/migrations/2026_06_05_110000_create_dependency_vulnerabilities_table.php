<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dependency_vulnerabilities', function (Blueprint $table) {
            $table->id();
            $table->string('ecosystem', 20)->comment('composer/npm');
            $table->string('package_name')->comment('包名');
            $table->string('installed_version')->comment('当前安装版本');
            $table->string('patched_version')->nullable()->comment('修复版本');
            $table->string('cve')->nullable()->comment('CVE 编号');
            $table->text('title')->nullable()->comment('漏洞标题');
            $table->text('description')->nullable()->comment('漏洞描述');
            $table->string('severity', 20)->default('medium')->comment('严重级别: critical/high/medium/low');
            $table->string('source', 30)->default('audit')->comment('来源: audit/dependabot/manual');
            $table->json('references')->nullable()->comment('参考链接');
            $table->string('status', 20)->default('open')->comment('状态: open/fixed/ignored/false_positive');
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('fixed_at')->nullable();
            $table->timestamps();

            $table->index(['ecosystem', 'status']);
            $table->index(['severity', 'status']);
            $table->index('cve');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependency_vulnerabilities');
    }
};

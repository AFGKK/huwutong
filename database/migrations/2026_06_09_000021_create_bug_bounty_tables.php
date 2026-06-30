<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 漏洞报告表
        if (!Schema::hasTable('bug_bounty_reports')) {
            Schema::create('bug_bounty_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
                $table->string('reporter_name', 100)->nullable()->comment('报告人姓名（可选）');
                $table->string('reporter_email', 255)->nullable()->comment('报告人邮箱');
                $table->string('reporter_handle', 100)->nullable()->comment('HackerOne/Bugcrowd 用户名');
                $table->string('title', 255)->comment('漏洞标题');
                $table->text('description')->comment('漏洞描述');
                $table->text('steps_to_reproduce')->nullable()->comment('复现步骤');
                $table->text('impact')->nullable()->comment('安全影响分析');
                $table->string('severity', 20)->default('medium')
                    ->comment('严重级别: critical/high/medium/low/informational');
                $table->string('vulnerability_type', 100)->nullable()->comment('漏洞类型: XSS/SQLI/CSRF/SSRF/RCE/IDOR/等等');
                $table->string('affected_endpoint', 255)->nullable()->comment('受影响端点/URL');
                $table->string('affected_version', 50)->nullable()->comment('受影响版本');
                $table->decimal('bounty_amount', 10, 2)->nullable()->comment('赏金金额');
                $table->string('bounty_currency', 3)->default('USD')->comment('赏金币种');
                $table->string('status', 30)->default('submitted')
                    ->comment('submitted/under_review/confirmed/fixed/declined/paid');
                $table->string('assigned_to', 100)->nullable()->comment('分配给');
                $table->string('resolution_notes', 1000)->nullable()->comment('处理备注');
                $table->boolean('is_public')->default(false)->comment('是否公开致谢');
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('fixed_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'status']);
                $table->index(['severity']);
            });
        }

        // 白帽子致谢表
        if (!Schema::hasTable('bug_bounty_hall_of_fame')) {
            Schema::create('bug_bounty_hall_of_fame', function (Blueprint $table) {
                $table->id();
                $table->string('hacker_name', 100)->comment('白帽子名称/代号');
                $table->string('hacker_handle', 100)->nullable()->comment('平台用户名');
                $table->string('avatar_url', 500)->nullable()->comment('头像/徽章URL');
                $table->unsignedSmallInteger('reports_count')->default(0)->comment('有效报告数');
                $table->decimal('total_bounty', 10, 2)->default(0)->comment('总赏金');
                $table->string('rank', 20)->default('bronze')
                    ->comment('排行: gold/silver/bronze/honorable');
                $table->text('bio')->nullable()->comment('简介');
                $table->json('acknowledged_reports')->nullable()->comment('致谢的报告ID列表');
                $table->boolean('is_featured')->default(false)->comment('是否重点展示');
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index(['rank', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bug_bounty_hall_of_fame');
        Schema::dropIfExists('bug_bounty_reports');
    }
};

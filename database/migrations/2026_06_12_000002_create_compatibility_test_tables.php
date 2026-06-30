<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 兼容性测试平台/环境定义
        Schema::create('compatibility_platforms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name', 100);
            $table->string('category', 50); // php, mysql, redis, browser, os, device
            $table->string('version', 50);
            $table->string('label', 200)->nullable(); // 显示名称，如 "PHP 8.3"
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'category', 'version']);
        });

        // 兼容性测试套件
        Schema::create('compatibility_test_suites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name', 200);
            $table->string('slug', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('category', 50); // integration, browser, api, performance
            $table->json('tags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        // 兼容性测试用例
        Schema::create('compatibility_test_cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('suite_id');
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('expected_result', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_critical')->default(false);
            $table->timestamps();

            $table->foreign('suite_id')->references('id')->on('compatibility_test_suites')->cascadeOnDelete();
        });

        // 兼容性测试运行
        Schema::create('compatibility_test_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('reference', 32);
            $table->string('status', 20)->default('pending'); // pending, running, passed, failed, error, cancelled
            $table->unsignedSmallInteger('total_tests')->default(0);
            $table->unsignedSmallInteger('passed_tests')->default(0);
            $table->unsignedSmallInteger('failed_tests')->default(0);
            $table->unsignedSmallInteger('skipped_tests')->default(0);
            $table->text('summary')->nullable();
            $table->string('triggered_by', 50)->nullable(); // manual, cron, webhook
            $table->unsignedBigInteger('triggered_by_user_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable(); // 额外数据（CI 链接、环境信息等）
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('triggered_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'status']);
        });

        // 兼容性测试运行 × 平台矩阵
        Schema::create('compatibility_matrix_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('test_run_id');
            $table->unsignedBigInteger('platform_id');
            $table->string('result', 20)->default('pending'); // pending, passed, failed, skipped, na
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('test_run_id', 'fk_matrix_run')->references('id')->on('compatibility_test_runs')->cascadeOnDelete();
            $table->foreign('platform_id', 'fk_matrix_platform')->references('id')->on('compatibility_platforms')->cascadeOnDelete();
            $table->unique(['test_run_id', 'platform_id']);
        });

        // 兼容性测试运行 × 用例结果
        Schema::create('compatibility_test_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('test_run_id');
            $table->unsignedBigInteger('platform_id');
            $table->unsignedBigInteger('test_case_id');
            $table->string('result', 20)->default('pending'); // pending, passed, failed, skipped
            $table->text('error_message')->nullable();
            $table->text('actual_output')->nullable();
            $table->decimal('execution_time_ms', 10, 2)->nullable();
            $table->unsignedBigInteger('tester_user_id')->nullable();
            $table->timestamps();

            $table->foreign('test_run_id', 'fk_result_run')->references('id')->on('compatibility_test_runs')->cascadeOnDelete();
            $table->foreign('platform_id', 'fk_result_platform')->references('id')->on('compatibility_platforms')->cascadeOnDelete();
            $table->foreign('test_case_id', 'fk_result_case')->references('id')->on('compatibility_test_cases')->cascadeOnDelete();
            $table->unique(['test_run_id', 'platform_id', 'test_case_id'], 'uq_test_result');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compatibility_test_results');
        Schema::dropIfExists('compatibility_matrix_results');
        Schema::dropIfExists('compatibility_test_runs');
        Schema::dropIfExists('compatibility_test_cases');
        Schema::dropIfExists('compatibility_test_suites');
        Schema::dropIfExists('compatibility_platforms');
    }
};

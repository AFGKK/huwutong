<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // API 版本定义
        Schema::create('api_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20)->unique()->comment('版本号: v1, v2');
            $table->string('base_path', 50)->comment('基础路径: /api/v2');
            $table->string('name', 100)->nullable()->comment('版本名称: 当前稳定版');
            $table->string('status', 20)->default('active')->comment('active / deprecated / sunset / retired');
            $table->timestamp('deprecated_at')->nullable()->comment('标记废弃时间');
            $table->timestamp('sunset_at')->nullable()->comment('计划停用时间');
            $table->timestamp('retired_at')->nullable()->comment('实际停用时间');
            $table->text('changelog')->nullable()->comment('版本变更说明');
            $table->text('migration_guide')->nullable()->comment('迁移指南（link or text）');
            $table->text('deprecation_notice')->nullable()->comment('废弃通知（返回给客户端的 header）');
            $table->boolean('is_default')->default(false)->comment('是否默认版本');
            $table->timestamps();
        });

        // API 版本路由映射（记录哪些路由属于哪个版本）
        Schema::create('api_version_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_version_id')->constrained()->cascadeOnDelete();
            $table->string('method', 10)->comment('GET/POST/PUT/DELETE');
            $table->string('path', 200)->comment('路由路径（相对 base_path）');
            $table->string('route_name', 100)->nullable()->comment('路由名称');
            $table->string('controller', 200)->nullable()->comment('控制器类');
            $table->string('action', 100)->nullable()->comment('控制器方法');
            $table->boolean('is_deprecated')->default(false);
            $table->timestamps();

            $table->unique(['api_version_id', 'method', 'path']);
        });

        // API 版本调用统计（用于影响分析）
        Schema::create('api_version_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 10);
            $table->string('path', 200);
            $table->unsignedInteger('call_count')->default(1);
            $table->date('call_date');
            $table->timestamps();

            $table->unique(['api_version_id', 'tenant_id', 'method', 'path', 'call_date'], 'api_version_call_unique');
            $table->index(['api_version_id', 'call_date']);
            $table->index(['tenant_id', 'api_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_version_calls');
        Schema::dropIfExists('api_version_routes');
        Schema::dropIfExists('api_versions');
    }
};

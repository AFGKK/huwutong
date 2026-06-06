<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apm_requests', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10)->comment('HTTP 方法');
            $table->string('path', 500)->comment('请求路径');
            $table->string('route_name')->nullable()->comment('路由名称');
            $table->unsignedSmallInteger('status_code')->comment('HTTP 状态码');
            $table->float('duration_ms', 10, 2)->comment('请求耗时(毫秒)');
            $table->float('db_duration_ms', 10, 2)->default(0)->comment('数据库查询耗时');
            $table->unsignedSmallInteger('db_queries')->default(0)->comment('数据库查询次数');
            $table->float('cache_duration_ms', 10, 2)->default(0)->comment('缓存操作耗时');
            $table->unsignedSmallInteger('cache_hits')->default(0)->comment('缓存命中数');
            $table->float('external_duration_ms', 10, 2)->default(0)->comment('外部 HTTP 调用耗时');
            $table->unsignedSmallInteger('external_calls')->default(0)->comment('外部 HTTP 调用次数');
            $table->float('memory_mb', 8, 2)->default(0)->comment('内存占用(MB)');
            $table->boolean('is_slow')->default(false)->comment('是否慢请求');
            $table->string('slow_reason')->nullable()->comment('慢请求原因');
            $table->string('ip', 45)->nullable()->comment('客户端 IP');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->index()->comment('请求时间');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apm_requests');
    }
};

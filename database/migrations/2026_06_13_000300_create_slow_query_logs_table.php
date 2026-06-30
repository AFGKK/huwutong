<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('slow_query_logs')) {
            return;
        }
        Schema::create('slow_query_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sql_hash', 40)->index()->comment('SQL MD5 哈希，用于去重聚合');
            $table->text('sql_text')->comment('SQL 原文（可能截断）');
            $table->string('sql_type', 10)->comment('SELECT/INSERT/UPDATE/DELETE');
            $table->string('database_name', 64)->nullable()->comment('数据库名');
            $table->string('table_name', 128)->nullable()->comment('涉及的表名');
            $table->float('duration_ms', 10, 2)->comment('执行耗时(毫秒)');
            $table->unsignedInteger('rows_examined')->default(0)->comment('扫描行数');
            $table->unsignedInteger('rows_sent')->default(0)->comment('返回行数');
            $table->unsignedInteger('lock_time_ms')->default(0)->comment('锁等待时间(毫秒)');
            $table->text('stack_trace')->nullable()->comment('PHP 调用栈');
            $table->string('route_name', 128)->nullable()->comment('API 路由名');
            $table->string('request_path', 500)->nullable()->comment('请求路径');
            $table->string('request_method', 10)->nullable()->comment('HTTP 方法');
            $table->text('explain_result')->nullable()->comment('EXPLAIN 结果(JSON)');
            $table->text('suggestion')->nullable()->comment('优化建议');
            $table->boolean('is_resolved')->default(false)->comment('是否已处理');
            $table->timestamp('resolved_at')->nullable()->comment('处理时间');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete()->comment('处理人');
            $table->timestamp('occurred_at')->index()->comment('发生时间');
            $table->timestamps();

            $table->index(['sql_hash', 'occurred_at']);
            $table->index('duration_ms');
            $table->index('is_resolved');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slow_query_logs');
    }
};

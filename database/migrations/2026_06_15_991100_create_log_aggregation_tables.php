<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 日志聚合存储表（database 驱动模式）
        Schema::create('log_aggregation_indices', function (Blueprint $table) {
            $table->id();
            $table->string('index_name', 100)->comment('日志索引名');
            $table->string('source', 50)->comment('日志来源: laravel/nginx/mysql/redis');
            $table->string('level', 20)->nullable()->comment('日志级别');
            $table->timestamp('log_date')->comment('日志日期');
            $table->bigInteger('count')->default(0)->comment('日志条数');
            $table->json('sample')->nullable()->comment('样本数据');
            $table->timestamps();

            $table->unique(['index_name', 'log_date']);
            $table->index('source');
            $table->index('level');
            $table->index('log_date');
        });

        Schema::create('log_aggregation_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('index_id')->nullable();
            $table->string('trace_id', 64)->nullable()->index()->comment('调用链追踪ID');
            $table->string('channel', 50)->nullable()->comment('日志通道');
            $table->string('level', 20)->index()->comment('日志级别: debug/info/warning/error/critical');
            $table->string('message', 2000);
            $table->json('context')->nullable()->comment('上下文数据');
            $table->json('extra')->nullable()->comment('额外元数据');
            $table->string('file', 500)->nullable();
            $table->integer('line')->nullable();
            $table->string('ip', 45)->nullable()->index();
            $table->string('user_agent', 500)->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('request_method', 10)->nullable();
            $table->string('request_path', 500)->nullable();
            $table->integer('response_status')->nullable();
            $table->float('duration_ms')->nullable()->comment('请求耗时毫秒');
            $table->timestamp('logged_at')->index();
            $table->timestamps();

            $table->index(['level', 'logged_at']);
            $table->index(['tenant_id', 'logged_at']);
            $table->index(['request_path', 'logged_at']);
            $table->foreign('index_id')->references('id')->on('log_aggregation_indices')->nullOnDelete();
        });

        // 搜索保存/分享
        Schema::create('log_saved_searches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->json('filters')->comment('搜索条件');
            $table->boolean('is_shared')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_saved_searches');
        Schema::dropIfExists('log_aggregation_entries');
        Schema::dropIfExists('log_aggregation_indices');
    }
};

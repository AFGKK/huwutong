<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Webhook 端点每日统计聚合表
        Schema::create('webhook_endpoint_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('stat_date')->comment('统计日期');
            $table->unsignedInteger('total_events')->default(0)->comment('总事件数');
            $table->unsignedInteger('success_count')->default(0)->comment('成功数');
            $table->unsignedInteger('failed_count')->default(0)->comment('失败数');
            $table->unsignedInteger('retried_count')->default(0)->comment('重试次数');
            $table->unsignedInteger('dead_letter_count')->default(0)->comment('死信数');
            $table->unsignedInteger('total_attempts')->default(0)->comment('总投递尝试次数');
            $table->float('avg_response_time_ms')->default(0)->comment('平均响应时间(毫秒)');
            $table->float('p50_response_time_ms')->default(0)->comment('P50响应时间');
            $table->float('p95_response_time_ms')->default(0)->comment('P95响应时间');
            $table->float('p99_response_time_ms')->default(0)->comment('P99响应时间');
            $table->json('status_code_distribution')->nullable()->comment('HTTP状态码分布');
            $table->json('event_type_distribution')->nullable()->comment('事件类型分布');
            $table->timestamps();

            $table->unique(['webhook_endpoint_id', 'stat_date'], 'wh_stat_unique');
            $table->index(['tenant_id', 'stat_date']);
        });

        // 延迟监控记录表
        Schema::create('webhook_latency_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_event_id')->nullable()->constrained()->nullOnDelete();
            $table->float('response_time_ms')->default(0)->comment('响应时间');
            $table->unsignedSmallInteger('http_status_code')->nullable();
            $table->boolean('is_timeout')->default(false);
            $table->timestamp('recorded_at')->index()->comment('记录时间');
            $table->timestamps();

            $table->index(['webhook_endpoint_id', 'recorded_at']);
            $table->index(['tenant_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_latency_records');
        Schema::dropIfExists('webhook_endpoint_daily_stats');
    }
};

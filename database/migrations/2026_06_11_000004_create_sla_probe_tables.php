<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_probes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name', 200)->comment('拨测名称');
            $table->string('url', 500)->comment('拨测目标URL');
            $table->string('method', 10)->default('GET')->comment('HTTP方法');
            $table->json('headers')->nullable()->comment('自定义请求头');
            $table->text('body')->nullable()->comment('请求体');
            $table->string('expected_status', 10)->default('200-299')->comment('期望状态码范围');
            $table->string('expected_body_contains', 500)->nullable()->comment('期望响应体包含');
            $table->unsignedInteger('timeout_seconds')->default(10)->comment('超时时间(秒)');
            $table->unsignedInteger('interval_minutes')->default(5)->comment('拨测间隔(分钟)');
            $table->json('sla_targets')->nullable()->comment('SLA目标: {max_response_time, min_uptime, ...}');
            $table->boolean('is_active')->default(true);
            $table->string('last_status', 20)->nullable()->comment('上次状态: up/down/slow/error');
            $table->unsignedInteger('last_response_time_ms')->nullable()->comment('上次响应时间(ms)');
            $table->timestamp('last_probed_at')->nullable()->comment('上次拨测时间');
            $table->unsignedInteger('consecutive_failures')->default(0)->comment('连续失败次数');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('sla_probe_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_probe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained();
            $table->string('status', 20)->comment('状态: up/down/slow/error');
            $table->unsignedInteger('response_time_ms')->nullable()->comment('响应时间(ms)');
            $table->unsignedSmallInteger('http_status_code')->nullable()->comment('HTTP状态码');
            $table->text('error_message')->nullable()->comment('错误信息');
            $table->json('response_headers')->nullable()->comment('响应头');
            $table->unsignedInteger('response_size_bytes')->nullable()->comment('响应大小');
            $table->timestamp('probed_at')->useCurrent()->comment('拨测时间');
            $table->timestamps();

            $table->index(['sla_probe_id', 'probed_at']);
            $table->index(['tenant_id', 'status', 'probed_at']);
        });

        Schema::create('sla_probe_uptimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_probe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained();
            $table->date('record_date')->comment('统计日期');
            $table->string('period', 10)->default('daily')->comment('统计周期: daily/weekly/monthly');
            $table->unsignedInteger('total_checks')->default(0)->comment('总拨测次数');
            $table->unsignedInteger('success_checks')->default(0)->comment('成功次数');
            $table->unsignedInteger('failure_checks')->default(0)->comment('失败次数');
            $table->decimal('uptime_percentage', 5, 2)->default(0)->comment('可用性百分比');
            $table->unsignedInteger('avg_response_time_ms')->nullable()->comment('平均响应时间(ms)');
            $table->unsignedInteger('max_response_time_ms')->nullable()->comment('最大响应时间(ms)');
            $table->unsignedInteger('min_response_time_ms')->nullable()->comment('最小响应时间(ms)');
            $table->timestamps();

            $table->unique(['sla_probe_id', 'record_date', 'period'], 'probe_uptime_unique');
            $table->index(['tenant_id', 'record_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_probe_uptimes');
        Schema::dropIfExists('sla_probe_results');
        Schema::dropIfExists('sla_probes');
    }
};

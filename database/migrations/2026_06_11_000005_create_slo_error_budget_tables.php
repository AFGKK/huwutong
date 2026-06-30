<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slo_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name', 200)->comment('SLO名称');
            $table->string('slug', 200)->unique()->comment('唯一标识');
            $table->string('description')->nullable();
            $table->string('service_name', 200)->comment('目标服务');
            $table->string('sli_type', 50)->comment('SLI类型: latency/availability/throughput/error_rate');
            $table->decimal('target', 5, 2)->comment('SLO目标值(百分比), 如 99.9');
            $table->decimal('window_days', 5, 1)->default(30)->comment('滚动窗口天数');
            $table->json('burn_rate_alerts')->nullable()->comment('燃烧率告警配置: [{window, threshold}]');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('total_requests')->default(0)->comment('窗口内总请求数');
            $table->unsignedInteger('good_requests')->default(0)->comment('窗口内成功请求数');
            $table->decimal('current_sli', 5, 2)->nullable()->comment('当前SLI值');
            $table->decimal('remaining_budget', 8, 2)->nullable()->comment('剩余错误预算(分钟)');
            $table->decimal('burn_rate', 8, 2)->nullable()->comment('当前燃烧率');
            $table->timestamps();

            $table->index(['tenant_id', 'service_name']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('slo_budget_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slo_definition_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 30)->comment('事件类型: budget_exhausted/budget_warning/burn_rate_alert/budget_reset');
            $table->decimal('budget_remaining', 8, 2)->comment('剩余错误预算(分钟)');
            $table->decimal('burn_rate', 8, 2)->nullable()->comment('燃烧率');
            $table->json('context')->nullable()->comment('事件上下文');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['slo_definition_id', 'created_at']);
        });

        Schema::create('slo_daily_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slo_definition_id')->constrained()->cascadeOnDelete();
            $table->date('record_date');
            $table->unsignedInteger('total_requests')->default(0);
            $table->unsignedInteger('good_requests')->default(0);
            $table->unsignedInteger('bad_requests')->default(0);
            $table->decimal('sli', 5, 2)->nullable()->comment('当日SLI');
            $table->decimal('budget_consumed', 8, 2)->default(0)->comment('当日消耗错误预算(分钟)');
            $table->timestamps();

            $table->unique(['slo_definition_id', 'record_date'], 'slo_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slo_daily_records');
        Schema::dropIfExists('slo_budget_events');
        Schema::dropIfExists('slo_definitions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 数据中心/区域定义
        Schema::create('data_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('数据中心名称: AWS东京/Azure新加坡');
            $table->string('code', 50)->unique()->comment('区域代码: ap-northeast-1, ap-southeast-1');
            $table->string('region', 50)->comment('地理区域: asia, europe, us, oceania');
            $table->string('country_code', 5)->nullable()->comment('所在国家 ISO 3166-1 alpha-2');
            $table->string('city', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('base_url', 255)->nullable()->comment('该数据中心API基础URL');
            $table->string('health_check_url', 255)->nullable();
            $table->json('capabilities')->nullable()->comment('能力: [compute, storage, database, cache, queue]');
            $table->string('status', 20)->default('healthy')->comment('healthy/degraded/down/maintenance');
            $table->decimal('current_latency_ms', 10, 2)->nullable()->comment('当前延迟');
            $table->decimal('current_load', 5, 2)->nullable()->comment('当前负载 0-100');
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamps();
        });

        // 区域健康日志
        Schema::create('region_health_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_center_id')->constrained()->cascadeOnDelete();
            $table->decimal('latency_ms', 10, 2);
            $table->decimal('load', 5, 2)->nullable();
            $table->boolean('is_healthy')->default(true);
            $table->string('check_type', 50)->default('ping')->comment('ping/http/dns');
            $table->text('error_message')->nullable();
            $table->json('metrics')->nullable()->comment('额外指标: cpu, memory, disk, connections');
            $table->timestamp('checked_at')->useCurrent()->index();
            $table->timestamps();
        });

        // 故障切换规则
        Schema::create('failover_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->foreignId('primary_dc_id')->constrained('data_centers')->cascadeOnDelete();
            $table->foreignId('backup_dc_id')->constrained('data_centers')->cascadeOnDelete();
            $table->string('trigger_type', 50)->default('latency')->comment('latency/down/manual');
            $table->decimal('trigger_threshold_ms', 10, 2)->nullable()->comment('触发延迟阈值(ms)');
            $table->integer('failure_count_threshold')->default(3)->comment('连续失败次数阈值');
            $table->boolean('auto_failover')->default(false)->comment('是否自动故障切换');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_failover_at')->nullable();
            $table->string('status', 20)->default('active')->comment('active/failover/restoring/inactive');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 故障切换事件日志
        Schema::create('failover_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('failover_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('action', 50)->comment('failover/restore/manual_failover/manual_restore');
            $table->string('from_dc', 50);
            $table->string('to_dc', 50);
            $table->string('trigger_reason', 255)->nullable();
            $table->boolean('is_automatic')->default(false);
            $table->json('metrics_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failover_logs');
        Schema::dropIfExists('failover_rules');
        Schema::dropIfExists('region_health_logs');
        Schema::dropIfExists('data_centers');
    }
};

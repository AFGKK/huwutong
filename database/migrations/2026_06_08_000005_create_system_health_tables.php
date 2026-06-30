<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 系统健康度历史记录（定时快照）
        Schema::create('system_health_logs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('ok')->comment('整体状态: ok/degraded/down');
            $table->decimal('overall_score', 5, 2)->default(100)->comment('整体健康分 0-100');
            $table->decimal('db_latency_ms', 8, 2)->nullable()->comment('数据库延迟');
            $table->boolean('db_healthy')->default(true);
            $table->decimal('redis_latency_ms', 8, 2)->nullable()->comment('Redis延迟');
            $table->boolean('redis_healthy')->default(true);
            $table->boolean('cache_healthy')->default(true);
            $table->string('cache_driver', 20)->nullable();
            $table->boolean('queue_healthy')->default(true);
            $table->string('queue_connection', 20)->nullable();
            $table->integer('queue_size')->default(0)->comment('队列积压数');
            $table->decimal('memory_mb', 10, 2)->default(0)->comment('PHP内存使用(MB)');
            $table->decimal('peak_memory_mb', 10, 2)->default(0)->comment('PHP峰值内存(MB)');
            $table->decimal('disk_usage_percent', 5, 2)->default(0)->comment('磁盘使用率(%)');
            $table->decimal('disk_free_gb', 10, 2)->default(0)->comment('磁盘剩余(GB)');
            $table->integer('db_connections')->default(0)->comment('数据库活跃连接数');
            $table->integer('failed_jobs_count')->default(0)->comment('失败任务数');
            $table->json('circuit_breakers')->nullable()->comment('熔断器状态');
            $table->json('extra_metrics')->nullable()->comment('额外指标');
            $table->timestamp('snapped_at')->index()->comment('快照时间');
            $table->timestamps();

            $table->index(['snapped_at', 'status']);
            $table->index(['overall_score']);
        });

        // 健康告警配置
        Schema::create('system_health_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('metric', 50)->unique()->comment('指标名称: db_latency/disk/memory/queue_size');
            $table->string('label', 100)->comment('显示名称');
            $table->decimal('warning_threshold', 12, 2)->nullable()->comment('警告阈值');
            $table->decimal('critical_threshold', 12, 2)->nullable()->comment('严重阈值');
            $table->string('unit', 20)->nullable()->comment('单位: ms/%/MB/GB');
            $table->string('comparison', 10)->default('gt')->comment('比较方式: gt/lt/gte/lte');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 插入默认阈值
        $now = now();
        $defaults = [
            ['metric' => 'db_latency', 'label' => '数据库延迟', 'warning_threshold' => 500, 'critical_threshold' => 2000, 'unit' => 'ms', 'comparison' => 'gt'],
            ['metric' => 'redis_latency', 'label' => 'Redis延迟', 'warning_threshold' => 200, 'critical_threshold' => 1000, 'unit' => 'ms', 'comparison' => 'gt'],
            ['metric' => 'disk_usage', 'label' => '磁盘使用率', 'warning_threshold' => 80, 'critical_threshold' => 95, 'unit' => '%', 'comparison' => 'gt'],
            ['metric' => 'memory_usage', 'label' => 'PHP内存', 'warning_threshold' => 128, 'critical_threshold' => 256, 'unit' => 'MB', 'comparison' => 'gt'],
            ['metric' => 'queue_size', 'label' => '队列积压', 'warning_threshold' => 100, 'critical_threshold' => 500, 'unit' => '条', 'comparison' => 'gt'],
            ['metric' => 'failed_jobs', 'label' => '失败任务数', 'warning_threshold' => 10, 'critical_threshold' => 50, 'unit' => '个', 'comparison' => 'gt'],
            ['metric' => 'db_connections', 'label' => 'DB连接数', 'warning_threshold' => 50, 'critical_threshold' => 100, 'unit' => '个', 'comparison' => 'gt'],
        ];
        foreach ($defaults as &$d) {
            $d['created_at'] = $now;
            $d['updated_at'] = $now;
        }
        DB::table('system_health_thresholds')->insert($defaults);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_health_thresholds');
        Schema::dropIfExists('system_health_logs');
    }
};

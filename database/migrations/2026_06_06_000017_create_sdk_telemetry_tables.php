<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SDK 心跳记录表
        Schema::create('sdk_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sdk_version', 30)->nullable()->comment('SDK 版本号');
            $table->string('sdk_language', 20)->nullable()->comment('SDK 语言: php/node/python/java/go');
            $table->string('sdk_platform', 30)->nullable()->comment('运行平台: linux/windows/macos');
            $table->string('sdk_arch', 10)->nullable()->comment('架构: x86_64/arm64');
            $table->string('hostname', 100)->nullable()->comment('主机名');
            $table->string('ip_address', 45)->nullable()->comment('客户端 IP');
            $table->unsignedInteger('uptime_seconds')->nullable()->comment('SDK 运行时长');
            $table->string('runtime_version', 30)->nullable()->comment('运行时版本: PHP 8.2/Node 20');
            $table->json('health_status')->nullable()->comment('健康状态: {cpu,memory,disk}');
            $table->json('features_active')->nullable()->comment('当前激活的功能列表');
            $table->json('metrics')->nullable()->comment('自定义指标');
            $table->timestamp('reported_at')->comment('上报时间');
            $table->timestamps();

            $table->index(['license_id', 'reported_at']);
            $table->index(['tenant_id', 'reported_at']);
            $table->index(['sdk_language', 'sdk_version', 'reported_at']);
            $table->index('reported_at');
        });

        // SDK Telemetry 聚合统计表
        Schema::create('sdk_telemetry_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('metric_key', 100)->comment('指标键: active_licenses/sdk_versions/call_counts');
            $table->string('dimension', 50)->nullable()->comment('维度: sdk_language/sdk_version/platform');
            $table->string('dimension_value', 100)->nullable()->comment('维度值');
            $table->unsignedBigInteger('count')->default(0);
            $table->date('agg_date');
            $table->timestamps();

            $table->unique(['tenant_id', 'metric_key', 'dimension', 'dimension_value', 'agg_date'], 'telemetry_agg_unique');
            $table->index(['metric_key', 'agg_date']);
        });

        // SDK Telemetry 事件日志（脱敏后的使用统计）
        Schema::create('sdk_telemetry_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 50)->comment('事件类型: license_checked/feature_used/error_occurred/cache_hit');
            $table->string('event_name', 100)->nullable()->comment('事件名称');
            $table->json('event_data')->nullable()->comment('事件数据（脱敏）');
            $table->unsignedInteger('count')->default(1)->comment('事件次数（合并上报）');
            $table->timestamp('occurred_at')->comment('事件发生时间');
            $table->timestamps();

            $table->index(['tenant_id', 'event_type', 'occurred_at']);
            $table->index(['license_id', 'occurred_at']);
            $table->index('occurred_at');
        });

        // SDK 版本分布快照
        Schema::create('sdk_version_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sdk_language', 20);
            $table->string('sdk_version', 30);
            $table->unsignedInteger('instance_count')->default(0)->comment('实例数');
            $table->date('snapshot_date');
            $table->timestamps();

            $table->unique(['tenant_id', 'sdk_language', 'sdk_version', 'snapshot_date'], 'sdk_version_snap_unique');
            $table->index(['tenant_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdk_version_snapshots');
        Schema::dropIfExists('sdk_telemetry_events');
        Schema::dropIfExists('sdk_telemetry_aggregates');
        Schema::dropIfExists('sdk_heartbeats');
    }
};

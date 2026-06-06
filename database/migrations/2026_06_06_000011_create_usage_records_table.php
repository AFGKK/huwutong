<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('metric_key', 100);          // 计量指标键，如 api_call.device_verify
            $table->string('action', 100);              // 操作类型，如 activate / validate / revoke
            $table->string('window_type', 20);          // 时间窗类型：total / daily / monthly / custom
            $table->unsignedInteger('quantity')->default(1); // 计量数量
            $table->string('unit', 30)->default('count');   // 计量单位：count / bytes / seconds / tokens
            $table->json('context')->nullable();            // 上下文信息（IP/设备/用户代理等）
            $table->timestamp('recorded_at')->index();      // 实际发生时间（支持延迟上报）
            $table->timestamps();

            // 索引优化
            $table->index(['tenant_id', 'metric_key', 'recorded_at']);
            $table->index(['license_id', 'metric_key', 'recorded_at']);
            $table->index(['customer_id', 'metric_key', 'recorded_at']);
        });

        // 用量汇总表（预聚合，加速查询）
        Schema::create('usage_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('metric_key', 100);
            $table->string('period', 20);               // daily / monthly / yearly
            $table->date('period_start');                // 聚合周期起始日期
            $table->date('period_end');                  // 聚合周期结束日期
            $table->unsignedBigInteger('total_quantity')->default(0);
            $table->unsignedInteger('record_count')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'metric_key', 'period', 'period_start', 'license_id', 'customer_id'], 'usage_agg_unique');
            $table->index(['tenant_id', 'metric_key', 'period_start']);
        });

        // License 用量配额配置表
        Schema::create('usage_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('metric_key', 100);
            $table->string('window_type', 20);           // total / daily / monthly
            $table->unsignedBigInteger('quota_limit');   // 配额上限
            $table->string('action_on_exceed', 30)->default('block'); // block / warn / log
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'metric_key']);
            $table->index(['license_id', 'metric_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_quotas');
        Schema::dropIfExists('usage_aggregates');
        Schema::dropIfExists('usage_records');
    }
};

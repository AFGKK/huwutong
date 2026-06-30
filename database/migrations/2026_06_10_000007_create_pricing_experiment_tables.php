<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 定价实验表
        if (!Schema::hasTable('pricing_experiments')) {
            Schema::create('pricing_experiments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('name')->comment('实验名称');
                $table->string('slug')->unique()->comment('唯一标识');
                $table->text('description')->nullable();
                $table->string('status', 20)->default('draft')
                    ->comment('draft|running|paused|completed|cancelled');
                $table->string('experiment_type', 30)->default('pricing')
                    ->comment('pricing|discount|bundle|tier|promotion');
                $table->string('target_metric', 30)->default('conversion')
                    ->comment('衡量指标: conversion|revenue|retention|profit');
                $table->integer('confidence_level')->default(95)->comment('统计置信度 %');
                $table->integer('minimum_sample_size')->default(100)->comment('最小样本量');
                $table->integer('sample_size')->default(0)->comment('当前样本量');
                $table->decimal('traffic_split', 5, 2)->default(50.00)->comment('实验组流量占比 %');
                $table->json('control_config')->nullable()->comment('对照组配置');
                $table->json('treatment_config')->nullable()->comment('实验组配置');
                $table->json('segment_filters')->nullable()->comment('客户分群筛选');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->json('results')->nullable()->comment('实验结果统计');
                $table->json('metadata')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'status']);
                $table->index('slug');
            });
        }

        // 实验参与者表（记录哪些客户/订阅参与了哪个实验组）
        if (!Schema::hasTable('pricing_experiment_participants')) {
            Schema::create('pricing_experiment_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('experiment_id')->constrained('pricing_experiments')->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
                $table->string('group', 20)->comment('control|treatment');
                $table->decimal('original_price', 12, 2)->nullable()->comment('原始价格');
                $table->decimal('experiment_price', 12, 2)->nullable()->comment('实验价格');
                $table->decimal('revenue_impact', 12, 2)->default(0)->comment('收入影响');
                $table->json('behavior_data')->nullable()->comment('行为数据');
                $table->timestamp('assigned_at')->nullable();
                $table->timestamps();

                $table->index(['experiment_id', 'group']);
                $table->index('customer_id');
            });
        }

        // 实验事件表（记录实验期间的关键事件）
        if (!Schema::hasTable('pricing_experiment_events')) {
            Schema::create('pricing_experiment_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('experiment_id')->constrained('pricing_experiments')->cascadeOnDelete();
                $table->foreignId('participant_id')->nullable()->constrained('pricing_experiment_participants')->nullOnDelete();
                $table->string('event_type', 30)
                    ->comment('viewed|converted|churned|upgraded|downgraded|cancelled');
                $table->text('event_data')->nullable();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();

                $table->index(['experiment_id', 'event_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_experiment_events');
        Schema::dropIfExists('pricing_experiment_participants');
        Schema::dropIfExists('pricing_experiments');
    }
};

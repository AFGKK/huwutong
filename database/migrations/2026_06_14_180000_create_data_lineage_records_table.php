<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('data_lineage_records')) {
            return;
        }
        Schema::create('data_lineage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // 溯源对象：License / Customer / Device 等
            $table->string('trackable_type', 120)->index();      // e.g. 'license', 'customer', 'device'
            $table->string('trackable_id', 64)->index();          // 对象标识
            $table->string('trackable_label', 255)->nullable();   // 可读标签（如 License Key 脱敏值）

            // 数据分类
            $table->string('data_category', 50)->index();         // 'license_key', 'pii', 'device_fingerprint'
            $table->string('sensitivity', 20)->default('internal'); // 'public','internal','confidential','restricted'

            // 事件
            $table->string('event_type', 60)->index();            // 'created','read','updated','exported','archived','deleted','drifted','activated','validated','revoked'
            $table->string('event_label', 255)->nullable();        // 可读描述

            // 来源 & 流向
            $table->string('source_system', 60)->nullable();       // 'system','api','admin','sdk','import','migration'
            $table->string('source_ip', 45)->nullable();
            $table->string('source_user_agent', 500)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 30)->nullable();          // 'user','system','api_key','webhook'
            $table->string('target_system', 60)->nullable();       // 数据流向目标

            // 变更详情
            $table->json('changes')->nullable();                   // 变更字段明细 [{field, old, new}]
            $table->json('metadata')->nullable();                  // 扩展元数据

            // 关联链路 - 用于追踪数据流转链
            $table->foreignId('parent_record_id')->nullable()->constrained('data_lineage_records')->nullOnDelete();
            $table->string('trace_id', 64)->nullable()->index();   // 同一请求链路 trace ID

            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            // 复合索引
            $table->index(['trackable_type', 'trackable_id', 'recorded_at'], 'lineage_lookup');
            $table->index(['data_category', 'event_type', 'recorded_at'], 'lineage_category');
            $table->index(['actor_id', 'actor_type', 'recorded_at'], 'lineage_actor');
            $table->index(['trace_id', 'recorded_at'], 'lineage_trace');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lineage_records');
    }
};

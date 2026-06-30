<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 监控组件（如 API / 数据库 / Redis / 消息队列）
        Schema::create('status_components', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('group', 50)->default('core')->comment('core/services/infrastructure/third_party');
            $table->string('status', 30)->default('operational')
                ->comment('operational/degraded_performance/partial_outage/major_outage/unknown');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        // 状态事件/事故
        Schema::create('status_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description');
            $table->string('severity', 20)->default('minor')->comment('minor/major/critical');
            $table->string('status', 30)->default('investigating')
                ->comment('investigating/identified/monitoring/resolved/postmortem');
            $table->boolean('is_public')->default(true);
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // 事件关联组件
        Schema::create('incident_component', function (Blueprint $table) {
            $table->foreignId('incident_id')->constrained('status_incidents')->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('status_components')->cascadeOnDelete();
            $table->primary(['incident_id', 'component_id']);
        });

        // 事件更新（investigating → identified → monitoring → resolved）
        Schema::create('incident_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('status_incidents')->cascadeOnDelete();
            $table->string('status', 30)->comment('investigating/identified/monitoring/resolved');
            $table->text('message');
            $table->timestamps();
        });

        // 订阅者
        Schema::create('status_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('token', 64)->unique()->comment('退订令牌');
            $table->boolean('is_active')->default(true);
            $table->timestamp('subscribed_at');
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });

        // 系统检查记录（定时写入，用于 uptime 计算）
        Schema::create('status_uptime_records', function (Blueprint $table) {
            $table->id();
            $table->string('component_slug', 100);
            $table->boolean('is_up');
            $table->integer('latency_ms')->nullable();
            $table->timestamp('checked_at');
            $table->index(['component_slug', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_uptime_records');
        Schema::dropIfExists('status_subscribers');
        Schema::dropIfExists('incident_updates');
        Schema::dropIfExists('incident_component');
        Schema::dropIfExists('status_incidents');
        Schema::dropIfExists('status_components');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 设备生命周期事件表
        if (!Schema::hasTable('device_lifecycle_events')) {
            Schema::create('device_lifecycle_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('device_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('event_type', 50)->comment('首次出现|信任建立|活跃稳定|异常行为|可疑标记|废弃');
                $table->string('stage', 30)->comment('new|onboarding|stable|suspicious|retired');
                $table->integer('trust_score_before')->nullable();
                $table->integer('trust_score_after')->nullable();
                $table->integer('trust_score_change')->nullable();
                $table->json('metadata')->nullable()->comment('事件上下文：IP变化、平台变化、触发原因等');
                $table->text('reason')->nullable();
                $table->string('triggered_by', 30)->default('system')
                    ->comment('system|admin|auto_detect');
                $table->foreignId('triggered_by_user')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['device_id', 'created_at']);
                $table->index(['tenant_id', 'event_type']);
                $table->index('stage');
            });
        }

        // 给 devices 表添加生命周期字段
        if (Schema::hasTable('devices')) {
            if (!Schema::hasColumn('devices', 'lifecycle_stage')) {
                Schema::table('devices', function (Blueprint $table) {
                    $table->string('lifecycle_stage', 30)->default('new')
                        ->after('is_virtual')
                        ->comment('new|onboarding|stable|suspicious|retired');
                    $table->integer('days_active')->default(0)->after('lifecycle_stage')
                        ->comment('活跃天数');
                    $table->integer('total_events')->default(0)->after('days_active')
                        ->comment('生命周期事件总数');
                    $table->timestamp('first_seen_at')->nullable()->after('total_events')
                        ->comment('首次出现时间');
                    $table->timestamp('last_stage_change_at')->nullable()->after('first_seen_at');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('devices')) {
            Schema::table('devices', function (Blueprint $table) {
                $columns = ['lifecycle_stage', 'days_active', 'total_events', 'first_seen_at', 'last_stage_change_at'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('devices', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        Schema::dropIfExists('device_lifecycle_events');
    }
};

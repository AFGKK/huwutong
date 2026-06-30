<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('on_call_schedules')) {
            Schema::create('on_call_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100)->comment('轮换名称');
            $table->string('description', 500)->nullable();
            $table->string('rotation_type', 20)->default('weekly')
                ->comment('轮换类型');
            $table->unsignedSmallInteger('rotation_length')->default(1)
                ->comment('轮换长度（与 rotation_type 配合）');
            $table->json('time_restriction')->nullable()
                ->comment('时间限制: {days:[1-7], start_time, end_time, timezone}');
            $table->json('escalation_rules')->nullable()
                ->comment('升级规则: [{after_minutes, notify_type, notify_target}]');
            $table->json('channels')->nullable()
                ->comment('通知渠道: [email, sms, slack, dingtalk, phone]');
            $table->string('status', 20)->default('active')->index()
                ->comment('状态: active/paused/archived');
            $table->string('color', 7)->default('#409eff')->comment('日历颜色');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            });
        }

        if (! Schema::hasTable('on_call_members')) {
            Schema::create('on_call_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('on_call_schedules')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0)->comment('轮换顺序');
            $table->unsignedTinyInteger('weight')->default(1)->comment('权重（高频人员更高）');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['schedule_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('on_call_entries')) {
            Schema::create('on_call_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('on_call_schedules')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('on_call_members')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at')->index()->comment('值班开始时间');
            $table->dateTime('ends_at')->index()->comment('值班结束时间');
            $table->string('role', 30)->default('primary')
                ->comment('角色: primary/backup/escalation');
            $table->string('status', 20)->default('scheduled')
                ->comment('状态: scheduled/active/completed/cancelled');
            $table->string('source', 20)->default('rotation')
                ->comment('来源: rotation/manual/override/swap');
            $table->timestamps();
            $table->index(['starts_at', 'ends_at']);
            $table->index(['user_id', 'status']);
            });
        }

        if (! Schema::hasTable('on_call_overrides')) {
            Schema::create('on_call_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('on_call_schedules')->cascadeOnDelete();
            $table->foreignId('original_user_id')->constrained('users');
            $table->foreignId('replacement_user_id')->constrained('users');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason', 200)->nullable()->comment('替换原因');
            $table->string('status', 20)->default('pending')
                ->comment('状态: pending/approved/rejected/cancelled');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['starts_at', 'ends_at']);
            });
        }

        if (! Schema::hasTable('on_call_logs')) {
            Schema::create('on_call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('on_call_entry_id')->nullable()->constrained('on_call_entries')->nullOnDelete();
            $table->foreignId('alert_event_id')->nullable()->constrained('alert_events')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30)->comment('动作: notified/escalated/resolved/timeout');
            $table->string('channel', 30)->nullable()->comment('通知渠道');
            $table->string('status', 20)->default('success')->comment('状态: success/failed/pending');
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index(['on_call_entry_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('on_call_logs');
        Schema::dropIfExists('on_call_overrides');
        Schema::dropIfExists('on_call_entries');
        Schema::dropIfExists('on_call_members');
        Schema::dropIfExists('on_call_schedules');
    }
};

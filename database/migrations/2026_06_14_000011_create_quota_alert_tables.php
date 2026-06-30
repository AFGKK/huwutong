<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quota_alerts')) {
            return;
        }
        // 配额预警配置表
        if (!Schema::hasTable('quota_alerts')) {
            Schema::create('quota_alerts', function (Blueprint $table) {
                $table->id();
                $table->morphs('alertable'); // customer/license/product
                $table->string('quota_type', 50)->comment('配额类型: device_count/api_calls/license_count/storage/bandwidth/seats');
                $table->unsignedInteger('quota_limit')->default(0)->comment('配额上限');
                $table->unsignedInteger('current_usage')->default(0)->comment('当前用量');
                $table->unsignedTinyInteger('usage_percent')->default(0)->comment('用量百分比');
                $table->string('level', 30)->default('normal')->comment('normal/warning/critical/exceeded');
                $table->boolean('notifications_enabled')->default(true);
                $table->boolean('auto_upgrade')->default(false)->comment('超限自动扩容');
                $table->timestamp('last_checked_at')->nullable()->comment('最近检查时间');
                $table->timestamp('last_notified_at')->nullable()->comment('最近通知时间');
                $table->timestamps();

                $table->index(['alertable_type', 'alertable_id', 'quota_type']);
                $table->index('level');
            });
        }

        // 预警通知日志表
        if (!Schema::hasTable('quota_alert_logs')) {
            Schema::create('quota_alert_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quota_alert_id')->constrained()->cascadeOnDelete();
                $table->string('quota_type', 50);
                $table->string('level', 30);
                $table->unsignedInteger('usage_percent');
                $table->unsignedInteger('current_usage');
                $table->unsignedInteger('quota_limit');
                $table->string('channel', 30)->default('in_app')->comment('通知渠道');
                $table->string('status', 30)->default('sent')->comment('sent/failed');
                $table->text('message')->nullable();
                $table->text('response')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_alert_logs');
        Schema::dropIfExists('quota_alerts');
    }
};

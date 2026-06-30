<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // License 时段限制配置表 (M3-77)
        if (!Schema::hasTable('time_restriction_configs')) {
            Schema::create('time_restriction_configs', function (Blueprint $table) {
                $table->id();
                $table->morphs('restrictable'); // license_id or product_id
                $table->boolean('is_active')->default(true)->comment('是否启用时段限制');
                $table->string('timezone', 50)->default('UTC')->comment('时区');

                // 每周可用时段（JSON: [{"day":1,"start":"09:00","end":"18:00"}, ...]）
                // day: 0=周日, 1=周一 ... 6=周六
                $table->json('weekly_schedule')->nullable()->comment('每周可用时段');

                // 特定期日/时段（JSON: [{"date":"2026-01-01","start":"10:00","end":"16:00"}, ...]）
                // 优先级高于 weekly_schedule
                $table->json('special_schedule')->nullable()->comment('特定期日时段');

                // 节假日配置（JSON: ["2026-01-01","2026-01-02", ...]）
                // 节假日默认不可用，除非有 special_schedule 覆盖
                $table->json('holidays')->nullable()->comment('节假日日期列表');

                // 非可用时段行为
                $table->string('out_of_hours_action', 20)->default('deny')->comment('deny=拒绝|grace=宽限|warn=警告');

                $table->unsignedSmallInteger('grace_minutes')->default(0)->comment('宽限分钟数（out_of_hours_action=grace时有效）');
                $table->string('allowed_ip_ranges')->nullable()->comment('例外IP范围（逗号分隔CIDR）');

                $table->text('description')->nullable();
                $table->timestamps();

                $table->index(['restrictable_type', 'restrictable_id', 'is_active'], 'tr_configs_type_id_active_idx');
            });
        }

        // 时段限制检查日志
        if (!Schema::hasTable('time_restriction_logs')) {
            Schema::create('time_restriction_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('config_id')->nullable()->index();
                $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
                $table->string('result', 20)->comment('allowed/denied/grace');
                $table->string('reason', 100)->nullable()->comment('拒绝原因');
                $table->string('ip_address', 45)->nullable();
                $table->string('timezone_used', 50)->nullable();
                $table->timestamp('checked_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('time_restriction_logs');
        Schema::dropIfExists('time_restriction_configs');
    }
};

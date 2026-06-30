<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('alert_silence_rules')) {
            return;
        }
        // 1. 静默规则
        Schema::create('alert_silence_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->comment('规则名称');
            $table->text('description')->nullable();
            $table->string('match_type', 30)->default('exact')->comment('exact/pattern/wildcard');
            $table->json('match_rules')->nullable()->comment('匹配条件 {rule_id?, severity?, source_type?, metric_type?, labels?}');
            $table->timestamp('starts_at')->comment('静默开始');
            $table->timestamp('ends_at')->comment('静默结束');
            $table->string('timezone', 50)->default('UTC');
            $table->boolean('is_recurring')->default(false)->comment('是否循环');
            $table->string('recurrence_rule', 100)->nullable()->comment('cron表达式');
            $table->string('created_by', 100)->nullable();
            $table->text('reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        // 2. 聚合日志
        Schema::create('alert_aggregation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_event_id')->constrained('alert_events')->cascadeOnDelete();
            $table->foreignId('child_event_id')->constrained('alert_events')->cascadeOnDelete();
            $table->string('group_key', 64)->index()->comment('聚合分组键');
            $table->string('reason', 50)->comment('similar_rule/same_source/duplicate_content');
            $table->timestamps();
        });

        // 3. 疲劳设置
        Schema::create('alert_fatigue_settings', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 50)->nullable()->unique()->comment('规则类型/来源');
            $table->unsignedSmallInteger('repetition_threshold')->default(5);
            $table->decimal('decay_factor', 4, 2)->default(0.50);
            $table->boolean('auto_downgrade')->default(true);
            $table->string('target_severity', 20)->default('info')->comment('降级目标级别');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. alert_rules 增加聚合/疲劳字段
        Schema::table('alert_rules', function (Blueprint $table) {
            $table->boolean('enable_aggregation')->default(true)->after('filters');
            $table->string('aggregation_group_by', 100)->nullable()->after('enable_aggregation');
            $table->unsignedSmallInteger('fatigue_threshold')->default(0)->after('aggregation_group_by');
            $table->string('noise_label', 50)->nullable()->after('fatigue_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('alert_rules', function (Blueprint $table) {
            $table->dropColumn(['enable_aggregation', 'aggregation_group_by', 'fatigue_threshold', 'noise_label']);
        });
        Schema::dropIfExists('alert_fatigue_settings');
        Schema::dropIfExists('alert_aggregation_logs');
        Schema::dropIfExists('alert_silence_rules');
    }
};

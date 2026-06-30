<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // IDS/IPS 检测规则表
        Schema::create('ids_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100)->comment('规则名称');
            $table->string('slug', 100)->unique()->comment('规则标识');
            $table->text('description')->nullable();

            // 检测类型
            $table->string('detection_type', 50)->default('brute_force')
                ->comment('检测类型: brute_force, geo_anomaly, rate_burst, suspicious_pattern, ip_reputation, credential_stuffing');

            // 触发条件 (JSON)
            $table->json('conditions')->comment('触发条件配置');
            // 响应动作 (JSON)
            $table->json('actions')->comment('响应动作配置');

            // 阈值配置
            $table->integer('threshold_count')->default(5)->comment('触发阈值次数');
            $table->integer('threshold_window_minutes')->default(5)->comment('检测时间窗口(分钟)');

            // 严重级别
            $table->string('severity', 20)->default('warning')
                ->comment('严重级别: info, warning, critical');

            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false)->comment('系统内置规则');
            $table->integer('priority')->default(100);

            // 统计
            $table->integer('hit_count')->default(0)->comment('命中次数');
            $table->timestamp('last_hit_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // IDS/IPS 检测告警记录
        Schema::create('ids_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ids_rule_id')->nullable()->constrained('ids_rules')->nullOnDelete();

            $table->string('rule_slug', 100)->nullable()->comment('规则标识(快照)');
            $table->string('rule_name', 100)->nullable();
            $table->string('detection_type', 50);
            $table->string('severity', 20)->default('warning');

            // 攻击来源
            $table->string('source_ip', 45)->nullable()->comment('攻击来源IP');
            $table->string('source_user_id', 100)->nullable();
            $table->string('target_resource', 200)->nullable()->comment('攻击目标');

            // 检测详情
            $table->json('evidence')->nullable()->comment('检测证据');
            $table->json('matched_conditions')->nullable();

            // 状态
            $table->string('status', 20)->default('open')
                ->comment('open, investigating, mitigated, false_positive, closed');
            $table->timestamp('mitigated_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // 关联 SOP 执行
            $table->foreignId('sop_execution_id')->nullable()->constrained('security_sop_executions')->nullOnDelete();

            $table->timestamps();
            $table->index(['tenant_id', 'status', 'detection_type']);
            $table->index(['source_ip', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ids_alerts');
        Schema::dropIfExists('ids_rules');
    }
};

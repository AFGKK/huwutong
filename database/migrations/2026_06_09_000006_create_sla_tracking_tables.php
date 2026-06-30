<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── SLA 合约 ───
        Schema::create('sla_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('slug', 100)->unique()->nullable();
            $table->text('description')->nullable();
            $table->string('level', 50)->default('standard')->comment('standard/premium/enterprise/custom');
            $table->json('scope')->nullable()->comment('覆盖范围 {modules, services, channels}');
            $table->json('terms')->nullable()->comment('SLA 条款 {response_time, resolution_time, uptime, availability}');
            $table->json('penalties')->nullable()->comment('违约处罚 {credits, discount, escalation}');
            $table->json('business_hours')->nullable()->comment('业务时间 {timezone, workdays, hours_start, hours_end}');
            $table->date('effective_date');
            $table->date('expiry_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_template')->default(false);
            $table->timestamps();

            $table->index(['customer_id', 'is_active']);
            $table->index(['tenant_id', 'level']);
        });

        // ─── SLA 指标 ───
        Schema::create('sla_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_contract_id')->constrained()->cascadeOnDelete();
            $table->string('metric_key', 80)->comment('response_time/resolution_time/uptime/availability/ticket_backlog');
            $table->string('name', 200);
            $table->string('unit', 30)->default('minutes')->comment('minutes/hours/percentage/count');
            $table->decimal('target_value', 12, 2)->comment('目标值');
            $table->decimal('warning_threshold', 5, 2)->nullable()->comment('告警阈值百分比');
            $table->string('measurement_window', 30)->default('monthly')->comment('daily/weekly/monthly/quarterly');
            $table->string('data_source', 50)->default('tickets')->comment('tickets/support/uptime/custom');
            $table->json('data_source_config')->nullable()->comment('数据源配置');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['sla_contract_id', 'metric_key']);
        });

        // ─── SLA 达标记录 ───
        Schema::create('sla_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sla_metric_id')->constrained()->cascadeOnDelete();
            $table->date('record_date');
            $table->string('period', 30)->default('daily')->comment('daily/weekly/monthly');
            $table->decimal('actual_value', 12, 2)->comment('实际值');
            $table->decimal('target_value', 12, 2)->comment('目标值');
            $table->decimal('compliance_rate', 5, 2)->comment('达标率百分比');
            $table->string('status', 30)->default('met')->comment('met/breached/warning/pending');
            $table->boolean('is_breached')->default(false);
            $table->json('details')->nullable()->comment('详细数据');
            $table->timestamps();

            $table->index(['sla_contract_id', 'record_date']);
            $table->index(['sla_metric_id', 'record_date']);
            $table->index(['is_breached', 'record_date']);
        });

        // ─── SLA 违约事件 ───
        Schema::create('sla_breaches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sla_metric_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('breach_type', 50)->comment('response_time/resolution_time/uptime/availability');
            $table->string('severity', 20)->default('minor')->comment('minor/major/critical');
            $table->nullableMorphs('breachable');
            $table->text('description');
            $table->decimal('expected_value', 12, 2);
            $table->decimal('actual_value', 12, 2);
            $table->decimal('deviation', 12, 2);
            $table->json('context')->nullable()->comment('上下文数据');
            $table->string('status', 30)->default('open')->comment('open/acknowledged/resolved/escalated');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['sla_contract_id', 'status']);
            $table->index(['severity', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_breaches');
        Schema::dropIfExists('sla_records');
        Schema::dropIfExists('sla_metrics');
        Schema::dropIfExists('sla_contracts');
    }
};

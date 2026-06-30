<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 收入确认排程表（ASC 606 / IFRS 15 递延收入）
        if (!Schema::hasTable('revenue_recognition_schedules')) {
            Schema::create('revenue_recognition_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
                $table->string('revenue_type', 30)->default('subscription')
                    ->comment('subscription/one_time/upgrade/credit');
                $table->string('billing_period', 20)->nullable()
                    ->comment('monthly/quarterly/semi_annually/yearly');
                $table->decimal('total_amount', 12, 2)->comment('总金额（待分期确认）');
                $table->decimal('recognized_amount', 12, 2)->default(0)->comment('已确认金额');
                $table->decimal('deferred_amount', 12, 2)->default(0)->comment('递延金额（未确认）');
                $table->string('currency', 3)->default('CNY');
                $table->date('start_date')->comment('确认开始日期');
                $table->date('end_date')->comment('确认结束日期');
                $table->unsignedSmallInteger('total_periods')->default(1)->comment('总期数');
                $table->unsignedSmallInteger('recognized_periods')->default(0)->comment('已完成期数');
                $table->string('recognition_method', 30)->default('straight_line')
                    ->comment('straight_line/upfront/deferred');
                $table->string('status', 20)->default('active')
                    ->comment('active/pending/completed/cancelled');
                $table->timestamp('last_recognized_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status', 'start_date']);
                $table->index(['invoice_id']);
            });
        }

        // 收入确认明细行
        if (!Schema::hasTable('revenue_recognition_lines')) {
            Schema::create('revenue_recognition_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('schedule_id')->constrained('revenue_recognition_schedules')->cascadeOnDelete();
                $table->unsignedSmallInteger('period_number')->comment('第几期');
                $table->date('recognition_date')->comment('确认日期');
                $table->decimal('amount', 12, 2)->comment('本期确认金额');
                $table->string('currency', 3)->default('CNY');
                $table->text('description')->nullable();
                $table->string('status', 20)->default('pending')->comment('pending/recognized/skipped');
                $table->timestamp('recognized_at')->nullable();
                $table->timestamps();

                $table->unique(['schedule_id', 'period_number'], 'rrl_schedule_period_unique');
                $table->index(['recognition_date', 'status']);
            });
        }

        // 月度收入汇总快照
        if (!Schema::hasTable('monthly_revenue_snapshots')) {
            Schema::create('monthly_revenue_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('year_month', 7)->comment('YYYY-MM');
                $table->decimal('invoiced_revenue', 14, 2)->default(0)->comment('发票收入');
                $table->decimal('recognized_revenue', 14, 2)->default(0)->comment('已确认收入');
                $table->decimal('deferred_revenue', 14, 2)->default(0)->comment('递延收入余额');
                $table->decimal('refunds', 14, 2)->default(0)->comment('当月退款');
                $table->decimal('net_new_arr', 14, 2)->default(0)->comment('新增ARR');
                $table->decimal('expansion_arr', 14, 2)->default(0)->comment('扩展ARR');
                $table->decimal('contraction_arr', 14, 2)->default(0)->comment('收缩ARR');
                $table->decimal('churned_arr', 14, 2)->default(0)->comment('流失ARR');
                $table->unsignedInteger('active_subscriptions')->default(0)->comment('活跃订阅数');
                $table->json('breakdown')->nullable()->comment('详细分类数据');
                $table->timestamps();

                $table->unique(['tenant_id', 'year_month']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_revenue_snapshots');
        Schema::dropIfExists('revenue_recognition_lines');
        Schema::dropIfExists('revenue_recognition_schedules');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('budget_limits')) {
            return;
        }
        if (!Schema::hasTable('budget_limits')) {
            Schema::create('budget_limits', function (Blueprint $table) {
                $table->id();
                $table->morphs('budgetable'); // tenant / customer
                $table->string('period', 20)->default('monthly')->comment('monthly/quarterly/yearly');
                $table->decimal('budget_amount', 14, 2)->default(0);
                $table->string('currency', 3)->default('CNY');
                $table->decimal('spent_amount', 14, 2)->default(0);
                $table->decimal('pending_amount', 14, 2)->default(0)->comment('待结算中的消费');
                $table->string('status', 20)->default('active')->comment('active/paused/expired');
                $table->timestamp('period_start_at')->nullable();
                $table->timestamp('period_end_at')->nullable();
                $table->timestamp('last_alert_at')->nullable();
                $table->boolean('notifications_enabled')->default(true);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('period');
                $table->index('status');
            });
        }

        if (!Schema::hasTable('budget_alerts')) {
            Schema::create('budget_alerts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('budget_limit_id');
                $table->string('level', 20)->comment('warning/critical/blocked');
                $table->decimal('usage_percentage', 5, 2)->comment('当时的用量百分比');
                $table->decimal('spent_at_alert', 14, 2)->default(0);
                $table->string('channel', 50)->nullable()->comment('通知渠道');
                $table->boolean('notified')->default(false);
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();

                $table->foreign('budget_limit_id')->references('id')->on('budget_limits')->onDelete('cascade');
                $table->index('level');
            });
        }

        if (!Schema::hasTable('budget_overrides')) {
            Schema::create('budget_overrides', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('budget_limit_id');
                $table->decimal('requested_amount', 14, 2)->default(0);
                $table->decimal('override_percentage', 5, 2)->comment('超出百分比');
                $table->string('reason', 500)->nullable();
                $table->string('status', 20)->default('pending')->comment('pending/approved/rejected/expired');
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->foreign('budget_limit_id')->references('id')->on('budget_limits')->onDelete('cascade');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_overrides');
        Schema::dropIfExists('budget_alerts');
        Schema::dropIfExists('budget_limits');
    }
};

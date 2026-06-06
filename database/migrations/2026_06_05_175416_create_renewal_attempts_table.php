<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renewal_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->tinyInteger('attempt_number')->default(1)->comment('第几次重试');
            $table->string('payment_method', 50)->nullable()->comment('本次使用的支付方式');
            $table->decimal('amount', 12, 2)->comment('尝试金额');
            $table->string('currency', 3)->default('CNY');
            $table->string('status', 20)->default('pending')->comment('pending/success/failed/skipped');
            $table->string('failure_reason', 255)->nullable()->comment('失败原因码');
            $table->text('failure_detail')->nullable()->comment('失败详情');
            $table->string('transaction_id', 255)->nullable();
            $table->json('retry_plan')->nullable()->comment('剩余重试计划快照');
            $table->boolean('escalated')->default(false)->comment('是否已升级到人工');
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'status']);
            $table->index(['subscription_id', 'attempt_number']);
        });

        // Renewal failure escalation tracking
        Schema::create('renewal_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 50)->comment('email/sms/phone/manual_review');
            $table->string('status', 20)->default('pending')->comment('pending/sent/acknowledged/resolved');
            $table->string('contact', 255)->nullable()->comment('通知联系人');
            $table->text('message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_note', 500)->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_escalations');
        Schema::dropIfExists('renewal_attempts');
    }
};

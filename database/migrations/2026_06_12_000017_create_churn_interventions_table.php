<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 流失干预记录表
        Schema::create('churn_interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50)->comment('干预类型：renewal_call/coupon_offer/training_session/executive_engagement/survey');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('assigned_to', 100)->nullable()->comment('责任人');
            $table->string('status', 30)->default('pending')->comment('pending/in_progress/completed/cancelled');
            $table->text('result')->nullable()->comment('干预结果描述');
            $table->string('outcome', 30)->nullable()->comment('positive/neutral/negative/unknown');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('churn_interventions');
    }
};

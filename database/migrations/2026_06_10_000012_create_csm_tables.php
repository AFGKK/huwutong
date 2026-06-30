<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CSM客户健康评分表
        if (!Schema::hasTable('csm_health_scores')) {
            Schema::create('csm_health_scores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->integer('health_score')->default(0)->comment('0-100 健康分');
                $table->string('health_level', 20)->default('at_risk')
                    ->comment('healthy|attention|at_risk|churned');
                $table->json('factors')->nullable()->comment('评分因素评分明细');
                $table->text('summary')->nullable()->comment('健康摘要');
                $table->timestamp('calculated_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'health_level']);
                $table->index(['customer_id', 'calculated_at']);
            });
        }

        // CSM跟进任务表
        if (!Schema::hasTable('csm_tasks')) {
            Schema::create('csm_tasks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('priority', 20)->default('normal')
                    ->comment('low|normal|high|urgent');
                $table->string('status', 20)->default('open')
                    ->comment('open|in_progress|completed|cancelled');
                $table->string('category', 50)->nullable()
                    ->comment('renewal|onboarding|support|review|checkin|custom');
                $table->string('related_type', 50)->nullable()
                    ->comment('renewal|churn|support_ticket|license_expiry|custom');
                $table->unsignedBigInteger('related_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'status', 'assigned_to']);
                $table->index(['customer_id', 'status']);
                $table->index(['assigned_to', 'status']);
                $table->index('due_at');
            });
        }

        // CSM客户沟通记录
        if (!Schema::hasTable('csm_communications')) {
            Schema::create('csm_communications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('type', 30)->comment('call|email|meeting|note|chat');
                $table->string('subject')->nullable();
                $table->text('content')->nullable();
                $table->json('attachments')->nullable();
                $table->timestamp('contacted_at')->useCurrent();
                $table->timestamps();

                $table->index(['customer_id', 'contacted_at']);
                $table->index(['tenant_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('csm_communications');
        Schema::dropIfExists('csm_tasks');
        Schema::dropIfExists('csm_health_scores');
    }
};

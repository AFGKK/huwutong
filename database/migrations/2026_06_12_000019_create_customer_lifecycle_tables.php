<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 客户生命周期阶段记录
        Schema::create('customer_lifecycle_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 40)->comment('生命周期阶段：prospect/onboarding/active/growing/mature/at_risk/churned');
            $table->string('previous_stage', 40)->nullable();
            $table->string('reason', 200)->nullable()->comment('阶段变更原因');
            $table->string('triggered_by', 100)->nullable()->comment('触发方式：auto/manual/workflow');
            $table->json('metadata')->nullable();
            $table->timestamp('entered_at')->useCurrent();
            $table->timestamp('exited_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'stage']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'entered_at']);
        });

        // 给 customers 表添加生命周期阶段字段
        Schema::table('customers', function (Blueprint $table) {
            $table->string('lifecycle_stage', 40)->default('prospect')->after('status');
            $table->timestamp('stage_entered_at')->nullable()->after('lifecycle_stage');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['lifecycle_stage', 'stage_entered_at']);
        });
        Schema::dropIfExists('customer_lifecycle_stages');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_segments')) {
            return;
        }
        Schema::create('customer_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('segment_key', 50)->unique()->comment('high_value_active/growth_potential/at_risk/new_onboarding/low_engagement');
            $table->string('name', 200);
            $table->string('color', 20)->default('#1890ff');
            $table->unsignedSmallInteger('priority')->default  (5);
            $table->json('criteria')->nullable()->comment('聚类特征范围');
            $table->unsignedInteger('customer_count')->default(0);
            $table->decimal('avg_score', 5, 2)->nullable();
            $table->json('recommended_actions')->nullable();
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_cluster_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('segment_key', 50);
            $table->decimal('score', 5, 2)->default(0)->comment('归属分数0-100');
            $table->json('features')->nullable()->comment('特征向量');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('previous_segment_at')->nullable();

            $table->index(['tenant_id', 'segment_key']);
            $table->index(['customer_id', 'segment_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_cluster_assignments');
    }
};

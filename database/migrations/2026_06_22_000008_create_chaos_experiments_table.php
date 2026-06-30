<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @m3-80 ChaosEngineering
     */
    public function up(): void
    {
        Schema::create('chaos_experiments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('experiment_type'); // redis_outage, db_failover, pod_kill, network_latency, disk_full, cpu_stress, memory_stress
            $table->string('target_service')->nullable(); // redis, database, api, queue, reverb, all
            $table->string('target_namespace')->nullable(); // K8s namespace
            $table->json('fault_config')->nullable(); // 故障参数配置
            $table->string('scope')->nullable(); // single_pod, multi_pod, service, namespace
            $table->string('blast_radius')->default('low'); // low, medium, high, critical
            $table->string('status')->default('draft'); // draft, scheduled, running, completed, failed, rolled_back
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->text('expected_behavior')->nullable();
            $table->text('actual_behavior')->nullable();
            $table->boolean('degradation_verified')->default(false);
            $table->boolean('auto_recovery_verified')->default(false);
            $table->integer('resilience_score')->nullable();
            $table->json('findings')->nullable();
            $table->json('improvements')->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('experiment_type');
            $table->index('target_service');
            $table->index('status');
            $table->index('blast_radius');
            $table->index('resilience_score');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chaos_experiments');
    }
};

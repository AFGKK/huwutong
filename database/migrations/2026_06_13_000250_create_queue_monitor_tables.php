<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('queue_monitor_logs')) {
            return;
        }
        Schema::create('queue_monitor_logs', function (Blueprint $table) {
            $table->id();
            $table->string('queue', 100)->index();
            $table->string('job_class', 500)->nullable();
            $table->string('status', 30)->comment('running/failed/completed');
            $table->float('duration_ms')->nullable();
            $table->unsignedInteger('attempt')->default(0);
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['queue', 'status']);
            $table->index('created_at');
        });

        Schema::create('queue_dead_letters', function (Blueprint $table) {
            $table->id();
            $table->string('queue', 100)->index();
            $table->string('job_class', 500);
            $table->json('payload');
            $table->text('last_error');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('status', 30)->default('dead')->comment('dead/retried/ignored');
            $table->timestamp('failed_at')->useCurrent();
            $table->timestamp('retried_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_dead_letters');
        Schema::dropIfExists('queue_monitor_logs');
    }
};

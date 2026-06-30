<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // LLM 健康检查记录
        Schema::create('llm_health_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('llm_provider_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_healthy');
            $table->integer('latency_ms')->nullable()->comment('延迟（毫秒）');
            $table->string('error_message')->nullable();
            $table->timestamp('checked_at');
            $table->index(['llm_provider_id', 'checked_at']);
        });

        // LLM 降级/恢复事件
        Schema::create('llm_fallback_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('llm_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 30)->comment('circuit_opened/circuit_closed/provider_switch/health_fail/health_recover/all_down');
            $table->string('from_provider')->nullable();
            $table->string('to_provider')->nullable();
            $table->text('reason')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
            $table->index('event_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_fallback_events');
        Schema::dropIfExists('llm_health_checks');
    }
};

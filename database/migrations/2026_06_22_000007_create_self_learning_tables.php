<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 交互日志：记录每次 AI 调用的完整信息
        if (! Schema::hasTable('ai_interaction_logs')) {
            Schema::create('ai_interaction_logs', function (Blueprint $table) {
                $table->id();
                $table->string('session_id', 64)->nullable()->index();
                $table->string('source_type', 50)->comment('ai_friend/rag/deep_research/hallucination/etc');
                $table->unsignedBigInteger('source_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->text('prompt')->comment('输入提示词');
                $table->longText('response')->nullable()->comment('AI 回复');
                $table->string('model', 100)->nullable();
                $table->string('provider', 50)->nullable();
                $table->float('temperature')->nullable();
                $table->integer('prompt_tokens')->default(0);
                $table->integer('completion_tokens')->default(0);
                $table->integer('total_tokens')->default(0);
                $table->integer('response_time_ms')->default(0);
                $table->float('quality_score')->nullable()->comment('后续评估的质量分 0~1');
                $table->boolean('was_helpful')->nullable()->comment('用户反馈是否有帮助');
                $table->boolean('had_hallucination')->nullable()->comment('是否检测到幻觉');
                $table->string('status', 20)->default('success')->comment('success/failed/blocked');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index('created_at');
            });
        }

        // 学习模式：从交互中提取的优化建议
        if (! Schema::hasTable('self_learning_patterns')) {
            Schema::create('self_learning_patterns', function (Blueprint $table) {
                $table->id();
                $table->string('pattern_type', 50)->comment('prompt_improvement/kb_gap/parameter_tuning/behavior_rule');
                $table->string('target', 200)->comment('优化目标，如 ai_friend.assistant.system_prompt');
                $table->text('current_value')->nullable()->comment('当前值');
                $table->text('suggested_value')->nullable()->comment('建议的新值');
                $table->float('confidence')->default(0)->comment('建议置信度 0~1');
                $table->text('evidence')->nullable()->comment('依据说明');
                $table->string('status', 20)->default('pending')->comment('pending/applied/rejected/auto');
                $table->unsignedBigInteger('applied_by')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();
                $table->index(['pattern_type', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('self_learning_patterns');
        Schema::dropIfExists('ai_interaction_logs');
    }
};

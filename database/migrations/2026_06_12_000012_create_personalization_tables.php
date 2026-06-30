<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 用户行为追踪
        Schema::create('user_behaviors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('event_type', 80)->comment('page_view/feature_use/license_action/purchase/login');
            $table->string('event_action', 200)->nullable()->comment('具体操作名');
            $table->string('resource_type', 80)->nullable()->comment('操作对象类型');
            $table->unsignedBigInteger('resource_id')->nullable()->comment('操作对象ID');
            $table->string('session_id', 100)->nullable()->comment('会话ID');
            $table->string('page_url', 500)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata')->nullable()->comment('额外数据');

            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'event_type', 'occurred_at'], 'ub_type_time_idx');
            $table->index(['tenant_id', 'customer_id', 'event_type'], 'ub_customer_type_idx');
        });

        // 用户偏好
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('preference_key', 100)->comment('preferred_layout/content_focus/notification_freq');
            $table->text('preference_value')->nullable();
            $table->string('preference_type', 30)->default('string')->comment('string/json/boolean/integer');
            $table->timestamps();

            $table->unique(['user_id', 'preference_key'], 'up_user_key_unique');
            $table->index(['tenant_id', 'preference_key'], 'up_tenant_key_idx');
        });

        // 个性化推荐
        Schema::create('personalized_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('recommendation_type', 50)->comment('license/feature/addon/article/product');
            $table->unsignedBigInteger('recommendable_id')->comment('推荐对象ID');
            $table->string('recommendable_type', 100)->comment('推荐对象模型');
            $table->string('reason', 200)->nullable()->comment('推荐理由');
            $table->decimal('score', 6, 4)->default(0)->comment('推荐分数 0-1');
            $table->string('source', 30)->default('rule')->comment('rule/rfm/behavior/llm');

            $table->boolean('is_dismissed')->default(false);
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id', 'recommendation_type'], 'pr_customer_type_idx');
            $table->index(['tenant_id', 'customer_id', 'is_dismissed', 'score'], 'pr_active_score_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personalized_recommendations');
        Schema::dropIfExists('user_preferences');
        Schema::dropIfExists('user_behaviors');
    }
};

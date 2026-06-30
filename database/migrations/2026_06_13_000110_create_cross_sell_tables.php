<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cross_sell_recommendations')) {
            return;
        }
        Schema::create('cross_sell_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('strategy', 50)->comment('usage_based/similar_customers/feature_adoption/complementary/popular');
            $table->string('recommendation_type', 30)->comment('upgrade/add_on/bundle/product');
            $table->morphs('recommendable'); // 推荐的目标产品/套餐
            $table->decimal('score', 5, 2)->default(0)->comment('0.00-1.00');
            $table->decimal('confidence', 5, 2)->default(0);
            $table->string('reason', 500)->nullable()->comment('推荐理由');
            $table->json('context')->nullable()->comment('推荐上下文数据');
            $table->string('status', 30)->default('pending')->comment('pending/shown/clicked/converted/dismissed');
            $table->timestamp('shown_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status', 'score']);
            $table->index(['tenant_id', 'strategy']);
        });

        Schema::create('cross_sell_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cross_sell_recommendation_id')->constrained('cross_sell_recommendations')->cascadeOnDelete();
            $table->string('event_type', 30)->comment('shown/clicked/converted/dismissed');
            $table->json('event_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cross_sell_events');
        Schema::dropIfExists('cross_sell_recommendations');
    }
};

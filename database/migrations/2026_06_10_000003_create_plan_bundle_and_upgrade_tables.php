<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 套餐捆绑规则
        if (!Schema::hasTable('bundle_plans')) {
            Schema::create('bundle_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_plan_id')->constrained('pricing_plans')->cascadeOnDelete();
                $table->foreignId('included_plan_id')->constrained('pricing_plans')->cascadeOnDelete();
                $table->string('type', 30)->default('optional')->comment('optional|required|upgrade');
                $table->decimal('discount_percent', 5, 2)->default(0)->comment('捆绑折扣百分比');
                $table->decimal('fixed_discount', 12, 2)->nullable()->comment('固定折扣金额');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['parent_plan_id', 'included_plan_id'], 'bundle_plans_pair_unique');
            });
        }

        // 套餐升降级比例折算规则
        if (!Schema::hasTable('plan_upgrade_paths')) {
            Schema::create('plan_upgrade_paths', function (Blueprint $table) {
                $table->id();
                $table->foreignId('from_plan_id')->constrained('pricing_plans')->cascadeOnDelete();
                $table->foreignId('to_plan_id')->constrained('pricing_plans')->cascadeOnDelete();
                $table->decimal('proration_ratio', 5, 4)->comment('剩余价值折算比例: 已使用时间扣除比例');
                $table->decimal('additional_fee', 12, 2)->default(0)->comment('固定额外升级费');
                $table->boolean('allow_downgrade')->default(false)->comment('是否允许降级');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['from_plan_id', 'to_plan_id'], 'upgrade_path_pair_unique');
            });
        }

        // 升降级操作记录
        if (!Schema::hasTable('plan_upgrade_logs')) {
            Schema::create('plan_upgrade_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
        $table->foreignId('from_plan_id')->nullable()->constrained('pricing_plans')->nullOnDelete();
        $table->foreignId('to_plan_id')->constrained('pricing_plans')->cascadeOnDelete();
                $table->string('type', 20)->comment('upgrade|downgrade|crossgrade');
                $table->decimal('original_price', 12, 2);
                $table->decimal('new_price', 12, 2);
                $table->decimal('credit', 12, 2)->default(0)->comment('剩余价值抵扣');
                $table->decimal('charge', 12, 2)->default(0)->comment('补差价');
                $table->decimal('discount', 12, 2)->default(0);
                $table->string('status', 30)->default('pending')->comment('pending|completed|failed|rolled_back');
                $table->json('details')->nullable();
                $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_upgrade_logs');
        Schema::dropIfExists('plan_upgrade_paths');
        Schema::dropIfExists('bundle_plans');
    }
};

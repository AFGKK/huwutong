<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agent_monthly_snapshots')) {
            return;
        }
        // 代理商月度业绩快照
        Schema::create('agent_monthly_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('year_month', 7)->comment('格式: 2026-06');
            $table->decimal('revenue', 14, 2)->default(0)->comment('月营收');
            $table->decimal('commission_earned', 14, 2)->default(0)->comment('月佣金');
            $table->integer('new_subscriptions')->default(0)->comment('新增订阅数');
            $table->integer('new_referrals')->default(0)->comment('新推荐客户数');
            $table->integer('new_downline')->default(0)->comment('新增下级代理');
            $table->decimal('conversion_rate', 5, 2)->default(0)->comment('转化率 %');
            $table->timestamps();

            $table->unique(['agent_id', 'year_month']);
            $table->index('year_month');
        });

        // 代理商绩效评分配置
        Schema::create('agent_score_configs', function (Blueprint $table) {
            $table->id();
            $table->string('metric', 50)->unique()->comment('指标标识');
            $table->string('label')->comment('指标名称');
            $table->decimal('weight', 5, 2)->default(1.0)->comment('权重');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 代理商等级分成比例 (按产品/品类)
        Schema::create('agent_commission_rates', function (Blueprint $table) {
            $table->id();
            $table->string('level', 30)->comment('代理商等级');
            $table->string('product_type', 50)->default('*')->comment('产品类型，* 表示通用');
            $table->decimal('rate', 5, 2)->default(0)->comment('分成比例 %');
            $table->decimal('multi_level_rate', 5, 2)->default(0)->comment('多级分成比例 %');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['level', 'product_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_monthly_snapshots');
        Schema::dropIfExists('agent_score_configs');
        Schema::dropIfExists('agent_commission_rates');
    }
};

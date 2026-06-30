<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_limit_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('规则名称，如 激活API限流');
            $table->string('slug', 100)->unique()->comment('唯一标识，如 activate');
            $table->string('key_type', 30)->default('ip')->comment('ip/license/product/tenant/api/global/api_key');
            $table->unsignedInteger('max_attempts')->default(60);
            $table->unsignedInteger('window_seconds')->default(60);
            $table->unsignedInteger('decay_ms')->nullable()->comment('衰减毫秒数');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0)->comment('优先级，低值优先匹配');
            $table->text('description')->nullable();
            $table->json('conditions')->nullable()->comment('应用条件：{methods: ["POST"], paths: ["api/license/*"]}');
            $table->timestamps();

            $table->index(['slug', 'is_active']);
            $table->index('key_type');
        });

        // 限流使用统计（只保留汇总数据，不保留每个请求）
        Schema::create('rate_limit_stats', function (Blueprint $table) {
            $table->id();
            $table->string('rule_slug', 100)->index();
            $table->string('dimension', 60)->index()->comment('限流维度值，如 tenant:5');
            $table->unsignedInteger('hit_count')->default(0)->comment('命中次数');
            $table->unsignedInteger('blocked_count')->default(0)->comment('被限流次数');
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();
            $table->timestamps();

            $table->index(['rule_slug', 'dimension']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_limit_stats');
        Schema::dropIfExists('rate_limit_rules');
    }
};

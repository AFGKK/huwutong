<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 用户的 onboarding 进度
        Schema::create('user_onboarding_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('current_step', 50)->default('welcome')->comment('当前步骤: welcome/profile/tenant/product/api_key/complete');
            $table->json('completed_steps')->nullable()->comment('已完成步骤列表');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // 快速启动清单
        Schema::create('quick_start_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_key', 100)->comment('项目标识');
            $table->string('title', 200)->comment('显示标题');
            $table->text('description')->nullable()->comment('描述/引导');
            $table->string('category', 50)->default('getting_started')->comment('分类');
            $table->string('action_url', 500)->nullable()->comment('操作链接');
            $table->string('action_label', 100)->nullable()->comment('操作按钮文字');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'item_key']);
            $table->index(['user_id', 'is_completed']);
            $table->index(['tenant_id', 'category']);
        });

        // 入门教程
        Schema::create('tutorials', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique()->comment('唯一标识');
            $table->string('title', 200)->comment('教程标题');
            $table->text('description')->nullable();
            $table->string('category', 50)->default('getting_started')->comment('分类');
            $table->json('steps')->nullable()->comment('步骤列表 [{title, content, image_url}]');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        // 用户教程进度
        Schema::create('user_tutorial_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tutorial_id')->constrained()->cascadeOnDelete();
            $table->integer('current_step')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tutorial_id']);
        });

        // 添加 onboarding 相关字段到 users 表
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('onboarding_completed')->default(false)->after('phone_verified_at');
            $table->timestamp('onboarding_skipped_at')->nullable()->after('onboarding_completed');
            $table->string('onboarding_skip_reason', 200)->nullable()->after('onboarding_skipped_at');
            $table->json('preferences')->nullable()->after('onboarding_skip_reason')->comment('用户偏好');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['onboarding_completed', 'onboarding_skipped_at', 'onboarding_skip_reason', 'preferences']);
        });
        Schema::dropIfExists('user_tutorial_progress');
        Schema::dropIfExists('tutorials');
        Schema::dropIfExists('quick_start_items');
        Schema::dropIfExists('user_onboarding_progress');
    }
};

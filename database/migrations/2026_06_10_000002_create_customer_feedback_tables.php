<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 反馈主表
        if (!Schema::hasTable('customer_feedback')) {
            Schema::create('customer_feedback', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();

                $table->string('type', 30)->default('general')->comment('general|bug|feature_request|performance|ui_ux|other');
                $table->tinyInteger('rating')->nullable()->comment('满意度评分 1-5');
                $table->text('message');
                $table->string('subject')->nullable()->comment('反馈主题');

                // 页面上下文
                $table->string('page_url')->nullable();
                $table->string('page_title')->nullable();
                $table->string('component_path')->nullable()->comment('触发反馈的组件路径');

                // 浏览器/系统上下文（自动收集）
                $table->string('user_agent')->nullable();
                $table->string('browser')->nullable();
                $table->string('os')->nullable();
                $table->string('screen_resolution')->nullable();
                $table->string('language')->nullable();
                $table->string('ip_address', 45)->nullable();

                // 截图/附件
                $table->json('screenshots')->nullable()->comment('截图路径数组');
                $table->json('attachments')->nullable()->comment('附件路径数组');
                $table->json('annotations')->nullable()->comment('标注意见: [{x,y,text,color}]');

                // 状态
                $table->string('status', 30)->default('new')->comment('new|under_review|acknowledged|in_progress|resolved|closed|wont_fix');
                $table->string('priority', 20)->default('normal')->comment('low|normal|high|critical');
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('assigned_at')->nullable();
                $table->text('admin_reply')->nullable()->comment('管理员回复');
                $table->timestamp('replied_at')->nullable();
                $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['customer_id', 'status']);
                $table->index(['type', 'status']);
                $table->index('priority');
                $table->index('created_at');
            });
        }

        // 反馈标签表（可选多标签）
        if (!Schema::hasTable('feedback_tags')) {
            Schema::create('feedback_tags', function (Blueprint $table) {
                $table->id();
                $table->string('name', 50);
                $table->string('color', 7)->default('#409eff');
                $table->timestamps();
            });
        }

        // 反馈-标签关联表
        if (!Schema::hasTable('customer_feedback_tags')) {
            Schema::create('customer_feedback_tags', function (Blueprint $table) {
                $table->foreignId('feedback_id')->constrained('customer_feedback')->cascadeOnDelete();
                $table->foreignId('tag_id')->constrained('feedback_tags')->cascadeOnDelete();
                $table->primary(['feedback_id', 'tag_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_feedback_tags');
        Schema::dropIfExists('feedback_tags');
        Schema::dropIfExists('customer_feedback');
    }
};

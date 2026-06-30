<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 认证等级定义
        Schema::create('certification_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('level_order')->default(0); // 等级排序
            $table->string('icon_url', 500)->nullable();             // 徽章图标
            $table->string('color', 20)->nullable();                 // 徽章颜色
            $table->unsignedInteger('passing_score')->default(70);   // 通过分数(%)
            $table->json('requirements')->nullable();                // 认证要求（前置条件、考试ID等）
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        // 考试题库
        Schema::create('exam_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('certification_level_id');
            $table->string('question', 1000);
            $table->string('type', 20)->default('single_choice'); // single_choice, multiple_choice, true_false
            $table->json('options');                               // [{id, text, is_correct}]
            $table->text('explanation')->nullable();               // 答案解析
            $table->unsignedSmallInteger('points')->default(1);    // 分值
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('certification_level_id', 'fk_exam_q_level')
                  ->references('id')->on('certification_levels')->cascadeOnDelete();
        });

        // 开发者认证记录
        Schema::create('developer_certifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('certification_level_id');
            $table->string('certificate_number', 32)->unique();    // 证书编号
            $table->string('status', 20)->default('in_progress');  // in_progress, passed, failed, expired, revoked
            $table->unsignedInteger('score')->nullable();           // 最终得分
            $table->unsignedInteger('total_points')->nullable();    // 总分
            $table->unsignedSmallInteger('attempts')->default(1);   // 尝试次数
            $table->unsignedSmallInteger('max_attempts')->default(3); // 最大尝试次数
            $table->boolean('badge_issued')->default(false);        // 是否已颁发徽章
            $table->string('badge_url', 500)->nullable();           // 徽章图片URL
            $table->timestamp('exam_started_at')->nullable();
            $table->timestamp('exam_completed_at')->nullable();
            $table->timestamp('certificate_issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();           // 认证到期日（通常1年）
            $table->json('metadata')->nullable();                  // 额外数据
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('certification_level_id', 'fk_dev_cert_level')
                  ->references('id')->on('certification_levels')->cascadeOnDelete();

            $table->unique(['user_id', 'certification_level_id']);
        });

        // 考试答题记录
        Schema::create('exam_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('developer_certification_id');
            $table->unsignedBigInteger('question_id');
            $table->json('selected_answers');          // 用户选择的答案ID数组
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('points_earned')->default(0);
            $table->timestamps();

            $table->foreign('developer_certification_id', 'fk_exam_answer_cert')
                  ->references('id')->on('developer_certifications')->cascadeOnDelete();
            $table->foreign('question_id', 'fk_exam_answer_q')
                  ->references('id')->on('exam_questions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_answers');
        Schema::dropIfExists('developer_certifications');
        Schema::dropIfExists('exam_questions');
        Schema::dropIfExists('certification_levels');
    }
};

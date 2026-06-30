<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nps_surveys')) {
            return;
        }
        // NPS 调查表（每次发送的调查记录）
        if (!Schema::hasTable('nps_surveys')) {
            Schema::create('nps_surveys', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('customer_id')->nullable()->comment('关联客户');
                $table->string('status', 30)->default('pending')->comment('pending/sent/completed/expired');
                $table->string('channel', 30)->default('email')->comment('email/in-app/popup');
                $table->timestamp('sent_at')->nullable()->comment('发送时间');
                $table->timestamp('completed_at')->nullable()->comment('完成时间');
                $table->timestamp('expires_at')->nullable()->comment('过期时间');
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index('sent_at');
            });
        }

        // NPS 响应表（用户的评分和反馈）
        if (!Schema::hasTable('nps_responses')) {
            Schema::create('nps_responses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('survey_id')->constrained('nps_surveys')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('score')->comment('NPS 评分 0-10');
                $table->text('feedback')->nullable()->comment('开放式反馈');
                $table->string('best_feature', 500)->nullable()->comment('最喜欢的功能');
                $table->string('improvement', 500)->nullable()->comment('改进建议');
                $table->string('category', 50)->nullable()->comment('promoter/passive/detractor');
                $table->ipAddress('ip_address')->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamps();

                $table->index('score');
                $table->index('category');
            });
        }

        // NPS 汇总快照表（定时汇总统计数据）
        if (!Schema::hasTable('nps_summaries')) {
            Schema::create('nps_summaries', function (Blueprint $table) {
                $table->id();
                $table->date('snapshot_date')->unique()->comment('快照日期');
                $table->unsignedInteger('total_responses')->default(0);
                $table->unsignedInteger('promoters')->default(0);
                $table->unsignedInteger('passives')->default(0);
                $table->unsignedInteger('detractors')->default(0);
                $table->decimal('nps_score', 5, 1)->default(0)->comment('NPS 分数 -100~100');
                $table->unsignedInteger('response_rate')->default(0)->comment('响应率（千分比）');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nps_summaries');
        Schema::dropIfExists('nps_responses');
        Schema::dropIfExists('nps_surveys');
    }
};

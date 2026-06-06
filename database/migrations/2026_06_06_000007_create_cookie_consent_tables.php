<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cookie_consent_configs', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true)->comment('启用 Cookie 横幅');
            $table->string('position', 20)->default('bottom')->comment('横幅位置: top, bottom, center');
            $table->string('title', 200)->default('Cookie 设置')->comment('横幅标题');
            $table->text('description')->nullable()->comment('横幅说明');
            $table->string('accept_all_text', 100)->default('接受全部')->comment('接受全部按钮文字');
            $table->string('reject_all_text', 100)->default('拒绝全部')->comment('拒绝全部按钮文字');
            $table->string('customize_text', 100)->default('自定义设置')->comment('自定义按钮文字');
            $table->string('privacy_policy_url')->nullable()->comment('隐私政策链接');
            $table->string('privacy_policy_text', 100)->default('隐私政策')->comment('隐私政策链接文字');
            $table->json('categories')->nullable()->comment('Cookie 分类配置（名称/描述/必须/默认选中）');
            $table->string('consent_lifetime_days', 10)->default('365')->comment('同意记录有效期(天)');
            $table->string('theme', 20)->default('light')->comment('主题: light, dark, auto');
            $table->string('layout', 20)->default('bar')->comment('布局: bar, modal, floating');
            $table->json('additional_css')->nullable()->comment('自定义样式');
            $table->timestamps();
        });

        Schema::create('cookie_consent_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('ip', 45)->nullable();
            $table->string('action', 20)->comment('accepted, rejected, customized');
            $table->json('selected_categories')->nullable()->comment('用户选择的分类');
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookie_consent_logs');
        Schema::dropIfExists('cookie_consent_configs');
    }
};

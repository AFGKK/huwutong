<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('模板标识: license_activated, license_expiring, etc');
            $table->string('name')->comment('模板名称');
            $table->string('subject')->comment('邮件主题（支持变量）');
            $table->text('body_html')->comment('HTML 正文（支持变量）');
            $table->text('body_text')->nullable()->comment('纯文本正文（支持变量）');
            $table->string('locale', 10)->default('zh-CN')->comment('语言');
            $table->json('variables')->nullable()->comment('可用变量列表');
            $table->string('status')->default('draft')->comment('状态: draft/published');
            $table->timestamps();
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('notifiable');
            $table->string('template_code')->nullable();
            $table->string('from_email')->nullable();
            $table->string('to_email');
            $table->string('subject');
            $table->string('status')->default('pending')->comment('pending/sent/delivered/bounced/failed');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('email_templates');
    }
};

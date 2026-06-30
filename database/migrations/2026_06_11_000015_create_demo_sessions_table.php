<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique()->comment('会话令牌');
            $table->string('session_id', 100)->unique()->comment('浏览器session标识');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->unsignedTinyInteger('step')->default(0)->comment('当前引导步骤');
            $table->string('current_page', 100)->nullable()->comment('当前页面路径');
            $table->json('completed_actions')->nullable()->comment('已完成的操作列表');
            $table->json('demo_data')->nullable()->comment('演示数据引用');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('expires_at')->comment('过期时间(30min)');
            $table->timestamp('last_activity_at')->nullable();
            $table->string('status', 20)->default('active')->comment('active/completed/expired');
            $table->timestamps();

            $table->index(['token', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_sessions');
    }
};

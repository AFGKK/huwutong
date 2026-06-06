<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announce_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200)->comment('公告标题');
            $table->text('content')->nullable()->comment('公告内容（支持 HTML）');
            $table->string('type', 20)->default('info')->comment('类型: info, success, warning, danger');
            $table->string('position', 20)->default('top')->comment('位置: top, bottom');
            $table->boolean('can_close')->default(true)->comment('用户可关闭');
            $table->string('link_url')->nullable()->comment('跳转链接');
            $table->string('link_text')->nullable()->comment('链接文字');
            $table->json('roles')->nullable()->comment('可见角色列表，null=全部');
            $table->timestamp('starts_at')->nullable()->comment('开始展示时间');
            $table->timestamp('ends_at')->nullable()->comment('结束展示时间');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0)->comment('排序');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announce_banners');
    }
};

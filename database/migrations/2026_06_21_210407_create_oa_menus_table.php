<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oa_menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name', 40);
            $table->string('type', 20)->default('click')->comment('click=发送消息, view=跳转URL, miniprogram=小程序');
            $table->string('key', 128)->nullable()->comment('click类型的key或view类型的URL');
            $table->string('app_id', 50)->nullable()->comment('小程序app_id');
            $table->string('page_path', 255)->nullable()->comment('小程序页面路径');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('account_id')->references('id')->on('official_accounts')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('oa_menus')->onDelete('cascade');
            $table->index(['account_id', 'parent_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oa_menus');
    }
};

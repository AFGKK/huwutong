<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oa_auto_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('type', 20)->default('welcome')->comment('welcome=关注回复, keyword=关键词回复, default=默认回复');
            $table->string('keyword', 100)->nullable()->comment('关键词（关键词回复时使用）');
            $table->tinyInteger('match_type')->default(0)->comment('0=精确匹配, 1=模糊匹配');
            $table->text('content');
            $table->string('content_type', 20)->default('text')->comment('text=文本, image=图片, article=文章链接');
            $table->string('media_url', 500)->nullable()->comment('图片URL或文章链接');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('account_id')->references('id')->on('official_accounts')->onDelete('cascade');
            $table->index(['account_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oa_auto_replies');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oa_materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('type', 20)->default('image')->comment('image=图片, text=文本');
            $table->string('file_url', 500)->nullable()->comment('图片URL（图片类型）');
            $table->text('content')->nullable()->comment('文本内容（文本类型）');
            $table->string('file_name', 255)->nullable();
            $table->integer('file_size')->nullable()->comment('文件大小（字节）');
            $table->string('group', 50)->nullable()->comment('分组');
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('account_id')->references('id')->on('official_accounts')->onDelete('cascade');
            $table->index(['account_id', 'type', 'group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oa_materials');
    }
};

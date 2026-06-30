<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question')->comment('问题');
            $table->string('answer')->nullable()->comment('自动回复内容（可选）');
            $table->string('icon')->default('💬')->comment('显示图标');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_faqs');
    }
};

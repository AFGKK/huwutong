<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_emojis', function (Blueprint $table) {
            $table->id();
            $table->string('shortcode', 50)->unique()->comment('表情代码，如 hwt_love');
            $table->string('image_url', 500)->comment('图片 URL');
            $table->string('category', 50)->default('general')->comment('分类：general/funny/reaction/logo');
            $table->string('aliases', 500)->nullable()->comment('别名，逗号分隔');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete()->comment('上传者');
            $table->unsignedInteger('usage_count')->default(0)->comment('使用次数');
            $table->timestamps();

            $table->index('category');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_emojis');
    }
};

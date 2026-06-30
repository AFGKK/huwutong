<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete()->comment('所属租户，null=全局分类');
            $table->string('name')->comment('分类名称');
            $table->string('slug')->unique()->comment('唯一标识');
            $table->text('description')->nullable()->comment('描述');
            $table->string('icon')->nullable()->comment('图标');
            $table->string('image_url')->nullable()->comment('分类图片');
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->cascadeOnDelete()->comment('父分类');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};

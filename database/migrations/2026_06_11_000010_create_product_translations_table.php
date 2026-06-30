<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_translations', function (Blueprint $table) {
            $table->id();
            $table->morphs('translatable');
            $table->string('locale', 10)->comment('语言代码: zh_CN/en/ja/ko 等');
            $table->string('field', 100)->comment('字段名: name/description/features/tagline 等');
            $table->text('value')->nullable()->comment('翻译值');
            $table->boolean('is_auto_translated')->default(false)->comment('是否AI自动翻译');
            $table->timestamps();

            $table->unique(['translatable_type', 'translatable_id', 'locale', 'field'], 'product_translation_unique');
            $table->index(['translatable_type', 'translatable_id', 'locale'], 'product_translation_locale_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_translations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_translations')) {
            return;
        }
        Schema::create('product_translations', function (Blueprint $table) {
            $table->id();
            $table->string('translatable_type', 100)->comment('Product/Category');
            $table->unsignedBigInteger('translatable_id');
            $table->string('locale', 10);
            $table->string('name', 500)->nullable();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('seo_title', 200)->nullable();
            $table->text('seo_description')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_auto_translated')->default(false);
            $table->timestamps();

            $table->unique(['translatable_type', 'translatable_id', 'locale'], 'product_translation_unique');
            $table->index(['translatable_type', 'translatable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_translations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sensitive_words')) {
            return;
        }
        Schema::create('sensitive_words', function (Blueprint $table) {
            $table->id();
            $table->string('word', 100)->unique()->comment('敏感词');
            $table->string('replacement', 100)->default('***')->comment('替换文本');
            $table->string('category', 50)->default('general')->comment('分类：general/politics/ad/abuse/porn');
            $table->string('severity', 20)->default('medium')->comment('严重级别：low/medium/high/critical');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensitive_words');
    }
};

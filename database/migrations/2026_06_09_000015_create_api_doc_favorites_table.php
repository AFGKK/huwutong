<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 端点收藏表
        if (!Schema::hasTable('api_doc_favorites')) {
            Schema::create('api_doc_favorites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('endpoint_id')->constrained('api_doc_endpoints')->cascadeOnDelete();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'endpoint_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('api_doc_favorites');
    }
};

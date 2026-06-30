<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oa_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('user_id');
            $table->text('content');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('status', 20)->default('approved'); // approved, pending, rejected
            $table->timestamps();
            $table->foreign('article_id')->references('id')->on('oa_articles')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('oa_comments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oa_comments');
    }
};

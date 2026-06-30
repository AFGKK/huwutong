<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('likeable_type', 100);
            $table->unsignedBigInteger('likeable_id');
            $table->timestamps();
            $table->unique(['user_id', 'likeable_type', 'likeable_id'], 'likes_unique');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['likeable_type', 'likeable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('likes');
    }
};

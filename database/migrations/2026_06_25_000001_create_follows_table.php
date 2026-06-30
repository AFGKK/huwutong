<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('followable_type', 100);
            $table->unsignedBigInteger('followable_id');
            $table->timestamps();
            $table->unique(['user_id', 'followable_type', 'followable_id'], 'follows_unique');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['followable_type', 'followable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};

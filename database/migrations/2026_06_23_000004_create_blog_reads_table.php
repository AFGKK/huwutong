<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blog_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['blog_id', 'user_id']);
            $table->foreign('blog_id')->references('id')->on('blog_posts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_reads');
    }
};

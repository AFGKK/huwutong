<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oa_comments', function (Blueprint $table) {
            $table->string('image', 500)->nullable()->after('content');
            $table->boolean('is_pinned')->default(false)->after('status');
        });

        Schema::create('oa_comment_likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comment_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->unique(['comment_id', 'user_id']);
            $table->foreign('comment_id')->references('id')->on('oa_comments')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oa_comment_likes');
        Schema::table('oa_comments', function (Blueprint $table) {
            $table->dropColumn(['image', 'is_pinned']);
        });
    }
};

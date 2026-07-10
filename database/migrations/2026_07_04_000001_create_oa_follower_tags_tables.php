<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 粉丝标签定义
        if (Schema::hasTable('oa_follower_tags')) { return; }
        Schema::create('oa_follower_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('name', 50);
            $table->string('color', 20)->default('#409eff');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('official_accounts')->onDelete('cascade');
            $table->unique(['account_id', 'name']);
        });

        // 粉丝-标签关联
        if (Schema::hasTable('oa_follower_tag_relations')) { return; }
        Schema::create('oa_follower_tag_relations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tag_id');
            $table->unsignedBigInteger('follower_id'); // follows.id
            $table->timestamps();

            $table->foreign('tag_id')->references('id')->on('oa_follower_tags')->onDelete('cascade');
            $table->foreign('follower_id')->references('id')->on('follows')->onDelete('cascade');
            $table->unique(['tag_id', 'follower_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oa_follower_tag_relations');
        Schema::dropIfExists('oa_follower_tags');
    }
};

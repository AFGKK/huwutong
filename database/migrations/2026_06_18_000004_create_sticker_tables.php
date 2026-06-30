<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sticker_packs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->string('cover_url', 500)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('is_system')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('stickers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sticker_pack_id');
            $table->string('image_url', 500);
            $table->string('emoji', 20)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('sticker_pack_id')->references('id')->on('sticker_packs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stickers');
        Schema::dropIfExists('sticker_packs');
    }
};

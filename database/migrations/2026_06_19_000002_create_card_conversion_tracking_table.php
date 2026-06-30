<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_conversion_tracking', function (Blueprint $table) {
            $table->id();
            $table->string('trace_id', 64)->index();
            $table->string('card_type', 50);
            $table->unsignedBigInteger('message_id')->nullable();
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->unsignedBigInteger('receiver_id')->nullable();
            $table->string('event', 30); // send, view, click, convert
            $table->string('callback_id', 100)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_conversion_tracking');
    }
};

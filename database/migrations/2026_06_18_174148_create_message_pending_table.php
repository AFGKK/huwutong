<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_messages') || Schema::hasTable('message_pending')) {
            return;
        }

        Schema::create('message_pending', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('message_id');
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'message_id']);
            $table->index('user_id');
            $table->foreign('message_id')->references('id')->on('conversation_messages')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_pending');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('message_favorites')) {
            Schema::create('message_favorites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('message_id');
                $table->timestamps();
                $table->unique(['user_id', 'message_id']);
                if (Schema::hasTable('conversation_messages')) {
                    $table->foreign('message_id')
                        ->references('id')
                        ->on('conversation_messages')
                        ->cascadeOnDelete();
                } else {
                    $table->index('message_id');
                }
            });
        }

        if (! Schema::hasTable('message_pendings')) {
            Schema::create('message_pendings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('message_id');
                $table->string('note', 500)->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'message_id']);
                if (Schema::hasTable('conversation_messages')) {
                    $table->foreign('message_id')
                        ->references('id')
                        ->on('conversation_messages')
                        ->cascadeOnDelete();
                } else {
                    $table->index('message_id');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_pendings');
        Schema::dropIfExists('message_favorites');
    }
};

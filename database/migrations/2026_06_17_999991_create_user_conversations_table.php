<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_conversations')) {
            return;
        }

        Schema::create('user_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('private')->comment('private, group');
            $table->string('name')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->integer('slow_mode_interval')->nullable();
            $table->boolean('join_approval')->default(false);
            $table->json('permissions')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_conversations');
    }
};

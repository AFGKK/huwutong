<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_messages')) {
            Schema::create('conversation_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('sender_id')->nullable();
                $table->string('message_type', 50)->default('text');
                $table->longText('content')->nullable();
                $table->json('attachments')->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('reply_to_id')->nullable();
                $table->boolean('is_edited')->default(false);
                $table->timestamp('edited_at')->nullable();
                $table->softDeletes();
                $table->string('client_msg_id')->nullable()->unique();
                $table->unsignedBigInteger('thread_parent_id')->nullable();
                $table->integer('thread_reply_count')->default(0);
                $table->boolean('is_pinned')->default(false);
                $table->timestamp('pinned_at')->nullable();
                $table->unsignedBigInteger('pinned_by')->nullable();
                $table->string('deliver_status')->default('sent');
                $table->timestamp('read_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->boolean('is_recalled')->default(false);
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->foreign('conversation_id')->references('id')->on('user_conversations')->cascadeOnDelete();
                $table->foreign('sender_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};

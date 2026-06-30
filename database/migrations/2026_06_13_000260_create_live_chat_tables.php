<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('live_chat_conversations')) {
            return;
        }
        Schema::create('live_chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 100)->unique();
            $table->string('status', 30)->default('active')->comment('active/waiting/handoff/closed');
            $table->string('source', 30)->default('portal')->comment('portal/widget/api');
            $table->string('department', 50)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('rating')->nullable()->comment('1-5');
            $table->text('rating_comment')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('session_id');
        });

        Schema::create('live_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('live_chat_conversations')->cascadeOnDelete();
            $table->string('sender_type', 20)->comment('user/agent/ai');
            $table->foreignId('sender_id')->nullable();
            $table->text('content');
            $table->json('metadata')->nullable()->comment('AI置信度/附件等');
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamps();

            $table->index(['conversation_id', 'sent_at']);
        });

        Schema::create('live_chat_handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('live_chat_conversations')->cascadeOnDelete();
            $table->string('reason', 100)->nullable();
            $table->string('status', 30)->default('pending')->comment('pending/accepted/rejected/closed');
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('handoff_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_chat_handoffs');
        Schema::dropIfExists('live_chat_messages');
        Schema::dropIfExists('live_chat_conversations');
    }
};

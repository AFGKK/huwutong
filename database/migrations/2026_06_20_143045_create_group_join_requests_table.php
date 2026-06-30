<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_conversations') && ! Schema::hasColumn('user_conversations', 'join_approval')) {
            Schema::table('user_conversations', function (Blueprint $table) {
                $table->boolean('join_approval')->default(false)->after('slow_mode_interval');
            });
        }

        if (! Schema::hasTable('user_conversations') || Schema::hasTable('group_join_requests')) {
            return;
        }

        Schema::create('group_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('user_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('message')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_join_requests');

        if (Schema::hasTable('user_conversations') && Schema::hasColumn('user_conversations', 'join_approval')) {
            Schema::table('user_conversations', function (Blueprint $table) {
                $table->dropColumn('join_approval');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_friend_profiles')) {
            Schema::create('ai_friend_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('visibility', 20)->default('global');
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('category', 50)->default('assistant');
                $table->string('welcome_message', 500)->nullable();
                $table->text('description')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->index('visibility');
                $table->index('category');
            });
        }

        if (! Schema::hasTable('ai_friend_llm_configs')) {
            Schema::create('ai_friend_llm_configs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ai_friend_id');
                $table->string('provider', 50)->default('deepseek');
                $table->string('model_name', 100)->default('deepseek-chat');
                $table->string('api_base_url', 500)->nullable();
                $table->text('api_key_encrypted')->nullable();
                $table->text('system_prompt')->nullable();
                $table->decimal('temperature', 3, 2)->default(0.7);
                $table->integer('max_tokens')->default(2048);
                $table->integer('context_window')->default(20);
                $table->boolean('stream_enabled')->default(true);
                $table->timestamps();

                $table->foreign('ai_friend_id')->references('id')->on('ai_friend_profiles')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('user_ai_contacts')) {
            Schema::create('user_ai_contacts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('ai_friend_id');
                $table->string('source', 30)->default('auto_global');
                $table->string('remark_name', 100)->nullable();
                $table->boolean('is_pinned')->default(false);
                $table->boolean('is_hidden')->default(false);
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('ai_friend_id')->references('id')->on('ai_friend_profiles')->cascadeOnDelete();
                $table->unique(['user_id', 'ai_friend_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_ai_contacts');
        Schema::dropIfExists('ai_friend_llm_configs');
        Schema::dropIfExists('ai_friend_profiles');
    }
};

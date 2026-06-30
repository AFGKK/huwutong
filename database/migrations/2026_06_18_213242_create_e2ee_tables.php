<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('e2ee_identity_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->text('public_key');       // X25519 base64
            $table->text('signed_prekey');     // Signed prekey public key base64
            $table->text('signature');         // Signature of signed prekey base64
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::create('e2ee_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('conversation_id');
            $table->text('session_key');       // AES-256 session key (encrypted with receiver's public key)
            $table->integer('ratchet_step')->default(0);
            $table->string('status', 20)->default('active'); // active/expired/revoked
            $table->timestamps();
            $table->unique(['user_id', 'conversation_id']);
            $table->index('user_id');
        });

        Schema::create('e2ee_one_time_prekeys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('key_id', 50);
            $table->text('public_key');        // One-time prekey public key base64
            $table->boolean('is_used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'is_used']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e2ee_one_time_prekeys');
        Schema::dropIfExists('e2ee_sessions');
        Schema::dropIfExists('e2ee_identity_keys');
    }
};

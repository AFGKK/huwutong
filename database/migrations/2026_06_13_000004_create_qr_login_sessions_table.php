<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_login_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 40)->unique()->index();
            $table->string('status', 20)->default('pending')->comment('pending/scanned/confirmed/expired');
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('confirmed_token', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_login_sessions');
    }
};

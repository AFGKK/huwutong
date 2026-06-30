<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name', 100);
            $table->string('avatar', 500)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('webhook_url', 500)->nullable();
            $table->string('token', 100)->unique();
            $table->json('commands')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index('user_id');
            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bots');
    }
};

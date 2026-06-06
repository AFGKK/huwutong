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
        Schema::create('user_auth_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->comment('提供商: email/phone/wechat/qq/alipay/google/github/apple');
            $table->string('provider_id')->comment('提供商用户ID');
            $table->string('avatar')->nullable()->comment('头像');
            $table->string('nickname')->nullable()->comment('昵称');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->index(['user_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_auth_providers');
    }
};

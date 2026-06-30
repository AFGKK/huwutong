<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sso_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('sso_provider_id')->constrained('sso_providers')->onDelete('cascade');
            $table->string('external_id')->comment('IdP 中的用户唯一标识');
            $table->string('external_email')->nullable()->comment('IdP 中的邮箱');
            $table->string('external_name')->nullable()->comment('IdP 中的用户名');
            $table->json('raw_attributes')->nullable()->comment('IdP 返回的原始属性');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['sso_provider_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_connections');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('credential_id', 512)->unique();
            $table->text('public_key');
            $table->string('type', 20)->default('public-key');
            $table->string('transport', 100)->nullable();
            $table->string('client_id', 255)->nullable()->comment('依赖方ID / RP ID');
            $table->string('aaguid', 36)->nullable()->comment('验证器AAGUID');
            $table->text('device_name')->nullable()->comment('设备名称（前端传递）');
            $table->integer('counter')->default(0)->comment('签名计数器');
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::create('webauthn_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('challenge', 128)->unique()->index();
            $table->string('type', 20)->comment('registration/authentication');
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_challenges');
        Schema::dropIfExists('webauthn_credentials');
    }
};

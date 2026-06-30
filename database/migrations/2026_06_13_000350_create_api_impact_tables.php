<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('api_impact_notifications')) {
            return;
        }
        Schema::create('api_impact_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_version_id')->constrained('api_versions')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 30)->default('email')->comment('email/in_app');
            $table->string('status', 20)->default('pending')->comment('pending/sent/failed');
            $table->text('message')->nullable();
            $table->json('context')->nullable()->comment('分析上下文');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_impact_notifications');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_token_usages')) {
            return;
        }
        Schema::create('ai_token_usages', function (Blueprint $table) {
            $table->id();
            $table->string('usage_type', 50)->comment('ai_agent/mcp_server');
            $table->unsignedBigInteger('usage_id');
            $table->string('model', 100)->nullable()->comment('模型名称');
            $table->unsignedInteger('tokens')->default(0);
            $table->unsignedInteger('requests')->default(1);
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->index(['usage_type', 'usage_id']);
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_token_usages');
    }
};

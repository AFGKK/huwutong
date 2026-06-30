<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_search_logs')) {
            return;
        }
        if (!Schema::hasTable('product_search_logs')) {
            Schema::create('product_search_logs', function (Blueprint $table) {
                $table->id();
                $table->string('keyword', 100)->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('ip', 45)->nullable();
                $table->timestamp('created_at')->nullable()->index();

                $table->index(['keyword', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_search_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_rules', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10)->index();
            $table->string('path')->index();
            $table->integer('status_code')->default(200);
            $table->json('response');
            $table->string('description')->nullable();
            $table->integer('delay_ms')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['method', 'path', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_rules');
    }
};

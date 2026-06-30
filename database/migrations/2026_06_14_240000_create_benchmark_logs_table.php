<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('benchmark_logs')) {
            return;
        }
        Schema::create('benchmark_logs', function (Blueprint $table) {
            $table->id();
            $table->string('message');
            $table->float('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmark_logs');
    }
};

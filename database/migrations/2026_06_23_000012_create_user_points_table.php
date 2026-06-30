<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('balance', 12, 2)->default(0)->comment('当前积分余额');
            $table->decimal('total_earned', 12, 2)->default(0)->comment('累计获得');
            $table->decimal('total_spent', 12, 2)->default(0)->comment('累计消费');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_points');
    }
};

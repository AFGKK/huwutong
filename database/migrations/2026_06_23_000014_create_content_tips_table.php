<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_tips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipper_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('tippable'); // tippable_type + tippable_id（自动创建索引）
            $table->decimal('points', 12, 2)->comment('打赏积分数量');
            $table->string('message', 500)->nullable()->comment('打赏附言');
            $table->timestamps();

            $table->index(['tipper_id', 'created_at']);
            $table->index(['receiver_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_tips');
    }
};

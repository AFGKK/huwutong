<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('forum_exp_logs')) { return; }
        Schema::create('forum_exp_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->integer('amount');
            $table->string('reason', 100);
            $table->integer('exp_before')->default(0);
            $table->integer('exp_after')->default(0);
            $table->integer('level_before')->default(1);
            $table->integer('level_after')->default(1);
            $table->timestamps();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_exp_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->constrained('customer_feedback')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('vote')->default(1)->comment('1=upvote, -1=downvote');
            $table->timestamps();

            $table->unique(['feedback_id', 'user_id'], 'fv_feedback_user_unique');
            $table->index(['feedback_id', 'vote']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_votes');
    }
};

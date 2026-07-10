<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('forum_post_earnings')) { return; }
        Schema::create('forum_post_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('forum_posts')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('price', 12, 2);
            $table->string('price_type', 20)->default('points'); // points | money
            $table->decimal('platform_fee', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('pending'); // pending | completed | refunded
            $table->string('purchase_table', 100)->nullable();
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->timestamps();

            $table->index(['author_id', 'status']);
            $table->index(['post_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_post_earnings');
    }
};

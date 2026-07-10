<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('oa_article_earnings')) { return; }
        Schema::create('oa_article_earnings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('buyer_id');
            $table->unsignedBigInteger('author_id');
            $table->decimal('price', 10, 2);
            $table->string('price_type', 20)->default('points'); // points | money
            $table->decimal('platform_fee', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2)->default(0);    // price - fee
            $table->string('status', 20)->default('pending');    // pending | settled | withdrawn
            $table->string('purchase_table')->nullable();        // polymorphic ref
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->timestamps();

            $table->foreign('article_id')->references('id')->on('oa_articles')->onDelete('cascade');
            $table->foreign('buyer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['author_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oa_article_earnings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // oa_articles 新增付费字段
        if (Schema::hasColumn('oa_articles', 'is_paid')) { return; }
        Schema::table('oa_articles', function (Blueprint $table) {
            $table->boolean('is_paid')->default(false)->after('allow_comments');
            $table->decimal('price', 10, 2)->default(0)->after('is_paid');
            $table->string('price_type', 20)->default('points')->after('price'); // points | money
        });

        // 文章购买记录
        if (Schema::hasTable('oa_article_purchases')) { return; }
        Schema::create('oa_article_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('price', 10, 2);
            $table->string('price_type', 20)->default('points');
            $table->string('status', 20)->default('completed'); // completed | refunded
            $table->timestamps();

            $table->foreign('article_id')->references('id')->on('oa_articles')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['article_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oa_article_purchases');
        Schema::table('oa_articles', function (Blueprint $table) {
            $table->dropColumn(['is_paid', 'price', 'price_type']);
        });
    }
};

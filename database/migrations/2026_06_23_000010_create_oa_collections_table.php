<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oa_collections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->string('cover_image')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('account_id')->references('id')->on('official_accounts')->onDelete('cascade');
            $table->index(['account_id', 'sort_order']);
        });

        if (!Schema::hasColumn('oa_articles', 'collection_id')) {
            Schema::table('oa_articles', function (Blueprint $table) {
                $table->unsignedBigInteger('collection_id')->nullable()->after('id');
                $table->foreign('collection_id')->references('id')->on('oa_collections')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('oa_articles', 'collection_id')) {
            Schema::table('oa_articles', function (Blueprint $table) {
                $table->dropForeign(['collection_id']);
                $table->dropColumn('collection_id');
            });
        }
        Schema::dropIfExists('oa_collections');
    }
};

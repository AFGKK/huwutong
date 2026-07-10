<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('oa_articles', 'is_global_pinned')) { return; }
        Schema::table('oa_articles', function (Blueprint $table) {
            $table->boolean('is_global_pinned')->default(false)->after('is_pinned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oa_articles', function (Blueprint $table) {
            $table->dropColumn('is_global_pinned');
        });
    }
};

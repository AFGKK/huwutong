<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oa_articles', function (Blueprint $table) {
            $table->boolean('is_original')->default(false)->after('is_pinned');
            $table->boolean('allow_comments')->default(true)->after('is_original');
            $table->json('images')->nullable()->after('cover_image');
        });
    }

    public function down(): void
    {
        Schema::table('oa_articles', function (Blueprint $table) {
            $table->dropColumn(['is_original', 'allow_comments', 'images']);
        });
    }
};

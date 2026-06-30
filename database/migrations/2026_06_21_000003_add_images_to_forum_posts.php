<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('forum_posts', 'images')) {
                $table->json('images')->nullable()->after('content');
            }
            // 让 title 可选（朋友圈不需要标题）
            $table->string('title', 200)->nullable()->change();
        });

        Schema::table('forum_replies', function (Blueprint $table) {
            if (! Schema::hasColumn('forum_replies', 'images')) {
                $table->json('images')->nullable()->after('content');
            }
        });
    }

    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropColumn('images');
            $table->string('title', 200)->nullable(false)->change();
        });
        Schema::table('forum_replies', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};

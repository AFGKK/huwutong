<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('forum_posts', 'is_paid')) { return; }
        Schema::table('forum_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('forum_posts', 'is_paid')) {
                $table->boolean('is_paid')->default(false);
                $table->decimal('price', 10, 2)->nullable()->after('is_paid');
                $table->string('price_type', 20)->default('points')->after('price');
                $table->text('content_preview')->nullable()->after('price_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropColumn(['is_paid', 'price', 'price_type', 'content_preview']);
        });
    }
};

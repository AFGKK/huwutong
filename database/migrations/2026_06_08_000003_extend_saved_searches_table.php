<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_searches', function (Blueprint $table) {
            $table->string('description', 200)->nullable()->after('name');
            $table->json('columns')->nullable()->after('filters')->comment('visible columns config');
            $table->json('sort')->nullable()->after('columns')->comment('sort config');
            $table->string('icon', 50)->nullable()->after('sort_order')->comment('icon name for quick access');
            $table->string('color', 20)->nullable()->after('icon')->comment('color for visual distinction');
            $table->unsignedInteger('usage_count')->default(0)->after('color')->comment('usage count');
            $table->timestamp('last_used_at')->nullable()->after('usage_count');
        });
    }

    public function down(): void
    {
        Schema::table('saved_searches', function (Blueprint $table) {
            $table->dropColumn(['description', 'columns', 'sort', 'icon', 'color', 'usage_count', 'last_used_at']);
        });
    }
};

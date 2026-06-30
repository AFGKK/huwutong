<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('product_categories', 'meta_title')) {
                $table->string('meta_title', 160)->nullable()->after('image_url');
            }
            if (!Schema::hasColumn('product_categories', 'meta_description')) {
                $table->string('meta_description', 500)->nullable()->after('meta_title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });
    }
};
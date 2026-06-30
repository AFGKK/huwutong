<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->string('template', 30)->default('discuss')->after('content')
                ->comment('discuss|poll|qa|checkin|announce');
        });
    }

    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropColumn('template');
        });
    }
};

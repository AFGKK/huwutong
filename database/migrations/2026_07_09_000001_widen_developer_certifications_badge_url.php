<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developer_certifications', function (Blueprint $table) {
            $table->text('badge_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('developer_certifications', function (Blueprint $table) {
            $table->string('badge_url', 500)->nullable()->change();
        });
    }
};

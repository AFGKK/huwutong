<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('official_accounts', 'verified_at')) { return; }
        Schema::table('official_accounts', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable()->after('settings');
            $table->unsignedBigInteger('verified_by')->nullable()->after('verified_at');
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('official_accounts', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['verified_at', 'verified_by']);
        });
    }
};

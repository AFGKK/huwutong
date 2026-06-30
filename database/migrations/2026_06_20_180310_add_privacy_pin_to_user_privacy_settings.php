<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_privacy_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('user_privacy_settings', 'privacy_pin')) {
                $table->string('privacy_pin', 100)->nullable()->after('allow_stranger_message')->comment('私密空间 PIN (bcrypt)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_privacy_settings', function (Blueprint $table) {
            $table->dropColumn('privacy_pin');
        });
    }
};

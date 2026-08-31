<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('fcm_token')->nullable()->after('remember_token');
            $table->string('fcm_platform', 16)->nullable()->after('fcm_token');
            $table->string('fcm_device_name', 255)->nullable()->after('fcm_platform');
            $table->timestamp('fcm_token_updated_at')->nullable()->after('fcm_device_name');

            $table->index('fcm_token', 'idx_users_fcm_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_fcm_token');
            $table->dropColumn(['fcm_token', 'fcm_platform', 'fcm_device_name', 'fcm_token_updated_at']);
        });
    }
};

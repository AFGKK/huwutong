<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            if (!Schema::hasColumn('channels', 'avatar')) {
                $table->string('avatar', 500)->nullable()->after('icon');
            }
            if (!Schema::hasColumn('channels', 'last_message_id')) {
                $table->unsignedBigInteger('last_message_id')->nullable()->after('avatar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'last_message_id']);
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('channel_messages', 'is_recalled')) {
            Schema::table('channel_messages', function (Blueprint $table) {
                $table->boolean('is_recalled')->default(false)->after('is_pinned');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('channel_messages', 'is_recalled')) {
            Schema::table('channel_messages', function (Blueprint $table) {
                $table->dropColumn('is_recalled');
            });
        }
    }
};

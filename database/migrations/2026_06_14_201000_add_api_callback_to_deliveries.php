<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->boolean('api_callback_sent')->default(false)->after('email_sent_at');
            $table->timestamp('api_callback_sent_at')->nullable()->after('api_callback_sent');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['api_callback_sent', 'api_callback_sent_at']);
        });
    }
};

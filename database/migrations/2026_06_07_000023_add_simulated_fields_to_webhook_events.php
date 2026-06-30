<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->boolean('is_simulated')->default(false)->after('next_retry_at')->index();
            $table->string('description', 500)->nullable()->after('next_retry_at');
            $table->integer('status_code')->nullable()->after('status');
            $table->text('response_body')->nullable()->after('status_code');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropColumn(['is_simulated', 'description', 'status_code', 'response_body']);
        });
    }
};

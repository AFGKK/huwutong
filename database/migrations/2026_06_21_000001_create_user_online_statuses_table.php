<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_online_statuses')) {
            Schema::create('user_online_statuses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->boolean('is_online')->default(false);
                $table->string('status')->nullable()->comment('online/away/busy/invisible');
                $table->string('custom_status')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->text('device_info')->nullable();
                $table->timestamp('last_sync_at')->nullable();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_online_statuses');
    }
};

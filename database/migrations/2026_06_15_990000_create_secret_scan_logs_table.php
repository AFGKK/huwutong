<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secret_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->string('file')->index();
            $table->string('pattern_label');
            $table->string('matched_preview', 50);
            $table->string('severity', 20)->default('medium');
            $table->string('status', 20)->default('open'); // open, dismissed, revoked
            $table->text('note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secret_scan_logs');
    }
};

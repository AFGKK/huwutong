<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('caller_id');
            $table->unsignedBigInteger('callee_id');
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('call_type', 10); // audio / video
            $table->string('status', 20)->default('calling'); // calling / connected / ended / missed / rejected
            $table->integer('duration')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['caller_id', 'status']);
            $table->index(['callee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};

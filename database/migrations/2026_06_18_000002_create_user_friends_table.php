<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_friends')) {
            if (Schema::hasTable('user_friends')) { return; }
        Schema::create('user_friends', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('requester_id');
                $table->unsignedBigInteger('addressee_id');
                $table->string('status', 20)->default('pending')->comment('pending, accepted, rejected, blocked');
                $table->string('remark')->nullable();
                $table->timestamps();

                $table->foreign('requester_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('addressee_id')->references('id')->on('users')->cascadeOnDelete();
                $table->unique(['requester_id', 'addressee_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_friends');
    }
};

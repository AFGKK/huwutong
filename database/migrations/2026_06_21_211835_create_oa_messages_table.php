<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oa_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('user_id')->comment('发送者（关注者）');
            $table->string('direction', 10)->default('in')->comment('in=关注者发送, out=号主回复');
            $table->text('content');
            $table->string('content_type', 20)->default('text')->comment('text, image');
            $table->string('media_url', 500)->nullable();
            $table->boolean('is_read')->default(false);
            $table->unsignedBigInteger('reply_to_id')->nullable()->comment('回复哪条消息');
            $table->timestamps();
            $table->foreign('account_id')->references('id')->on('official_accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['account_id', 'user_id', 'created_at']);
            $table->index(['account_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oa_messages');
    }
};

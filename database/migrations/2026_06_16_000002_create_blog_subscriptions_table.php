<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blog_subscriptions')) return;

        Schema::create('blog_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('email', 200);
            $table->string('name', 100)->nullable();
            $table->json('subscribed_types')->nullable()->comment('blog,changelog,release_note');
            $table->string('frequency', 20)->default('instant')->comment('instant,daily,weekly');
            $table->string('token', 100)->unique()->comment('验证/退订令牌');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_subscriptions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->string('avatar', 500)->nullable();
            $table->string('cover_image', 500)->nullable();
            $table->unsignedBigInteger('owner_id');
            $table->string('status', 20)->default('active');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('official_account_followers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->unique(['account_id', 'user_id']);
        });

        Schema::create('oa_articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('title', 200);
            $table->text('content');
            $table->string('cover_image', 500)->nullable();
            $table->string('summary', 300)->nullable();
            $table->json('tags')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reject_reason', 500)->nullable();
            $table->unsignedBigInteger('source_submission_id')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('oa_article_likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->unique(['article_id', 'user_id']);
        });

        Schema::create('oa_article_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('oa_article_shares', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('user_id');
            $table->string('platform', 50)->default('im');
            $table->timestamps();
        });

        Schema::create('oa_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('user_id');
            $table->string('title', 200);
            $table->text('content');
            $table->string('cover_image', 500)->nullable();
            $table->string('summary', 300)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reject_reason', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oa_submissions');
        Schema::dropIfExists('oa_article_shares');
        Schema::dropIfExists('oa_article_reads');
        Schema::dropIfExists('oa_article_likes');
        Schema::dropIfExists('oa_articles');
        Schema::dropIfExists('official_account_followers');
        Schema::dropIfExists('official_accounts');
    }
};

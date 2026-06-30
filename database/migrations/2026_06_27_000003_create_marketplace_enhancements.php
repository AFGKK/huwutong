<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 应用评价/评分表 ──
        if (!Schema::hasTable('marketplace_app_reviews')) {
            Schema::create('marketplace_app_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('app_id')->constrained('marketplace_apps')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
                $table->tinyInteger('rating')->unsigned(); // 1-5
                $table->text('content')->nullable();
                $table->string('status')->default('approved'); // pending / approved / rejected
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['app_id', 'user_id']);
                $table->index(['app_id', 'status']);
                $table->index(['user_id']);
            });
        }

        // ── 回复表（开发者回复评论） ──
        if (!Schema::hasTable('marketplace_app_review_replies')) {
            Schema::create('marketplace_app_review_replies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('review_id')->constrained('marketplace_app_reviews')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->text('content');
                $table->timestamps();

                $table->index('review_id');
            });
        }

        // ── marketplace_apps 补充字段 ──
        if (Schema::hasTable('marketplace_apps')) {
            Schema::table('marketplace_apps', function (Blueprint $table) {
                if (!Schema::hasColumn('marketplace_apps', 'screenshots')) {
                    $table->json('screenshots')->nullable()->after('icon_url');
                }
                if (!Schema::hasColumn('marketplace_apps', 'demo_video_url')) {
                    $table->string('demo_video_url')->nullable()->after('screenshots');
                }
                if (!Schema::hasColumn('marketplace_apps', 'license_info')) {
                    $table->string('license_info')->nullable()->after('repository_url');
                }
                if (!Schema::hasColumn('marketplace_apps', 'privacy_url')) {
                    $table->string('privacy_url')->nullable()->after('license_info');
                }
                if (!Schema::hasColumn('marketplace_apps', 'support_url')) {
                    $table->string('support_url')->nullable()->after('privacy_url');
                }
            });
        }

        // ── 应用分类表 ──
        if (!Schema::hasTable('marketplace_categories')) {
            Schema::create('marketplace_categories', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->string('description')->nullable();
                $table->string('icon')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('sort_order');
                $table->index('is_active');
            });
        }

        // ── 应用 Banner/推荐位表 ──
        if (!Schema::hasTable('marketplace_banners')) {
            Schema::create('marketplace_banners', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('subtitle')->nullable();
                $table->string('image_url');
                $table->string('link_type')->default('app'); // app / category / url
                $table->string('link_value')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['is_active', 'sort_order']);
            });
        }

        // ── 应用下载统计表 ──
        if (!Schema::hasTable('marketplace_download_logs')) {
            Schema::create('marketplace_download_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('app_id')->constrained('marketplace_apps')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action'); // view_detail / download / install / uninstall / update
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent')->nullable();
                $table->timestamps();

                $table->index(['app_id', 'created_at']);
                $table->index(['action', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_download_logs');
        Schema::dropIfExists('marketplace_banners');
        Schema::dropIfExists('marketplace_categories');
        Schema::table('marketplace_apps', function (Blueprint $table) {
            $table->dropColumn(['screenshots', 'demo_video_url', 'license_info', 'privacy_url', 'support_url']);
        });
        Schema::dropIfExists('marketplace_app_review_replies');
        Schema::dropIfExists('marketplace_app_reviews');
    }
};

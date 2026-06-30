<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_developers')) {
            Schema::create('marketplace_developers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('display_name', 100);
                $table->string('company_name', 200)->nullable();
                $table->string('website')->nullable();
                $table->text('description')->nullable();
                $table->string('status', 30)->default('pending')->comment('pending|active|suspended');
                $table->timestamp('verified_at')->nullable();
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique('user_id');
                $table->index('status');
            });
        }

        if (!Schema::hasTable('marketplace_apps')) {
            Schema::create('marketplace_apps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('developer_id')->constrained('marketplace_developers')->cascadeOnDelete();
                $table->string('slug', 100)->unique();
                $table->string('name', 200);
                $table->string('short_description', 500)->nullable();
                $table->text('description')->nullable();
                $table->string('category', 50)->default('integration');
                $table->string('icon_url')->nullable();
                $table->string('status', 30)->default('draft')->comment('draft|pending_review|published|rejected|suspended');
                $table->string('pricing_type', 20)->default('free')->comment('free|paid|subscription');
                $table->decimal('price', 10, 2)->default(0);
                $table->unsignedInteger('install_count')->default(0);
                $table->decimal('avg_rating', 3, 2)->default(0);
                $table->unsignedInteger('review_count')->default(0);
                $table->string('webhook_url')->nullable();
                $table->json('permissions')->nullable();
                $table->string('documentation_url')->nullable();
                $table->string('repository_url')->nullable();
                $table->string('current_version', 30)->nullable();
                $table->text('review_notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'category']);
            });
        }

        if (!Schema::hasTable('marketplace_app_versions')) {
            Schema::create('marketplace_app_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('app_id')->constrained('marketplace_apps')->cascadeOnDelete();
                $table->string('version', 30);
                $table->text('changelog')->nullable();
                $table->string('package_url')->nullable();
                $table->string('min_platform_version', 30)->nullable();
                $table->string('status', 20)->default('draft')->comment('draft|published|deprecated');
                $table->timestamp('released_at')->nullable();
                $table->timestamps();

                $table->unique(['app_id', 'version']);
            });
        }

        if (!Schema::hasTable('marketplace_app_review_logs')) {
            Schema::create('marketplace_app_review_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('app_id')->constrained('marketplace_apps')->cascadeOnDelete();
                $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 30)->comment('submit|approve|reject|request_changes|suspend');
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['app_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('marketplace_app_installations')) {
            Schema::create('marketplace_app_installations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('app_id')->constrained('marketplace_apps')->cascadeOnDelete();
                $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('status', 20)->default('active')->comment('active|uninstalled');
                $table->string('installed_version', 30)->nullable();
                $table->json('config')->nullable();
                $table->timestamp('installed_at')->nullable();
                $table->timestamp('uninstalled_at')->nullable();
                $table->timestamps();

                $table->unique(['app_id', 'tenant_id', 'user_id']);
                $table->index(['tenant_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_app_installations');
        Schema::dropIfExists('marketplace_app_review_logs');
        Schema::dropIfExists('marketplace_app_versions');
        Schema::dropIfExists('marketplace_apps');
        Schema::dropIfExists('marketplace_developers');
    }
};

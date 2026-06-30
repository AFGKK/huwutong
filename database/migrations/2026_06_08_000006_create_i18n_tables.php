<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Supported Languages ────────────────────────────────
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 20)->unique()->comment('e.g. zh_CN, en, ja');
            $table->string('name')->comment('e.g. 简体中文, English, 日本語');
            $table->string('native_name')->nullable()->comment('e.g. 简体中文');
            $table->string('flag')->nullable()->comment('Flag emoji or icon class');
            $table->string('direction', 3)->default('ltr')->comment('ltr | rtl');
            $table->boolean('is_rtl')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // ─── Translation Keys (Namespaces) ──────────────────────
        Schema::create('translation_namespaces', function (Blueprint $table) {
            $table->id();
            $table->string('namespace', 100)->unique()->comment('e.g. errors, messages, validation');
            $table->string('label')->nullable()->comment('Human-readable label');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('key_count')->default(0);
            $table->timestamps();
        });

        // ─── Translation Entries ────────────────────────────────
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('namespace_id')->constrained('translation_namespaces')->onDelete('cascade');
            $table->string('locale', 20);
            $table->string('key')->comment('Translation key (dot notation within namespace)');
            $table->text('value')->nullable()->comment('Translated text');
            $table->text('default_value')->nullable()->comment('Original/fallback text');
            $table->boolean('is_published')->default(false);
            $table->boolean('is_auto_translated')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['namespace_id', 'locale', 'key'], 'translation_unique');
            $table->index(['locale', 'is_published']);
        });

        // ─── Translation Import/Export Logs ─────────────────────
        Schema::create('translation_imports', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->comment('import | export');
            $table->string('format', 10)->comment('csv | json | php | xliff');
            $table->string('file_path')->nullable();
            $table->json('summary')->nullable()->comment('{total, created, updated, skipped, errors}');
            $table->string('status', 20)->default('pending')->comment('pending | processing | completed | failed');
            $table->text('error_message')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // ─── Translation Change History ─────────────────────────
        Schema::create('translation_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('translation_id')->constrained('translations')->onDelete('cascade');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('action', 20)->comment('updated | auto_translated | imported | published');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_histories');
        Schema::dropIfExists('translation_imports');
        Schema::dropIfExists('translations');
        Schema::dropIfExists('translation_namespaces');
        Schema::dropIfExists('languages');
    }
};

<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$db = \Illuminate\Support\Facades\DB::connection();

$sql = file_get_contents(__DIR__ . '/database/migrations/2026_06_20_000001_create_ai_memories_table.php');
// Extract CREATE TABLE SQL, but easier: just check and create manually

// ── ai_memories ──
if (!\Illuminate\Support\Facades\Schema::hasTable('ai_memories')) {
    \Illuminate\Support\Facades\Schema::create('ai_memories', function ($t) {
        $t->id();
        $t->foreignId('user_id')->constrained()->cascadeOnDelete();
        $t->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
        $t->string('key')->index();
        $t->text('content');
        $t->string('type', 30)->default('fact')->index();
        $t->string('source', 30)->default('ai_extracted')->index();
        $t->float('confidence', 8, 4)->default(0.8);
        $t->unsignedTinyInteger('priority')->default(0);
        $t->string('category', 50)->nullable()->index();
        $t->json('tags')->nullable();
        $t->nullableMorphs('memorable');
        $t->timestamp('expires_at')->nullable();
        $t->boolean('is_active')->default(true)->index();
        $t->timestamps();
        $t->softDeletes();
        $t->index(['user_id', 'type']);
        $t->index(['user_id', 'category']);
        $t->index(['user_id', 'is_active', 'expires_at'], 'idx_memories_active');
        $t->index(['user_id', 'confidence', 'priority'], 'idx_memories_importance');
    });
    echo "Created ai_memories\n";
}

// ── ai_insights ──
if (!\Illuminate\Support\Facades\Schema::hasTable('ai_insights')) {
    \Illuminate\Support\Facades\Schema::create('ai_insights', function ($t) {
        $t->id();
        $t->foreignId('user_id')->constrained()->cascadeOnDelete();
        $t->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
        $t->unsignedBigInteger('conversation_id')->nullable()->index();
        $t->unsignedBigInteger('message_id')->nullable()->index();
        $t->string('type', 30)->index();
        $t->string('title', 200);
        $t->text('content');
        $t->json('context')->nullable();
        $t->string('status', 20)->default('pending')->index();
        $t->timestamp('sent_at')->nullable();
        $t->timestamp('read_at')->nullable();
        $t->timestamp('dismissed_at')->nullable();
        $t->string('source', 30)->default('scan_job');
        $t->timestamps();
        $t->index(['user_id', 'status']);
        $t->index(['user_id', 'type']);
        $t->index(['user_id', 'created_at'], 'idx_insights_user_time');
    });
    echo "Created ai_insights\n";
}

// ── on_call tables (if schedules exist but members don't) ──
if (\Illuminate\Support\Facades\Schema::hasTable('on_call_schedules') && !\Illuminate\Support\Facades\Schema::hasTable('on_call_members')) {
    $db->statement("CREATE TABLE on_call_members (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        schedule_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        sort_order TINYINT UNSIGNED DEFAULT 0,
        weight TINYINT UNSIGNED DEFAULT 1,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL,
        UNIQUE KEY unique_schedule_user (schedule_id, user_id)
    )");
    $db->statement("CREATE TABLE on_call_entries (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        schedule_id BIGINT UNSIGNED NOT NULL,
        member_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        starts_at DATETIME NOT NULL,
        ends_at DATETIME NOT NULL,
        role VARCHAR(30) DEFAULT 'primary',
        status VARCHAR(20) DEFAULT 'scheduled',
        source VARCHAR(20) DEFAULT 'rotation',
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL
    )");
    $db->statement("CREATE TABLE on_call_overrides (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        schedule_id BIGINT UNSIGNED NOT NULL,
        original_user_id BIGINT UNSIGNED NOT NULL,
        replacement_user_id BIGINT UNSIGNED NOT NULL,
        starts_at DATETIME NOT NULL,
        ends_at DATETIME NOT NULL,
        reason VARCHAR(200) DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        approved_by BIGINT UNSIGNED DEFAULT NULL,
        approved_at TIMESTAMP NULL,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL
    )");
    $db->statement("CREATE TABLE on_call_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        on_call_entry_id BIGINT UNSIGNED DEFAULT NULL,
        alert_event_id BIGINT UNSIGNED DEFAULT NULL,
        user_id BIGINT UNSIGNED DEFAULT NULL,
        action VARCHAR(30) NOT NULL,
        channel VARCHAR(30) DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'success',
        details JSON DEFAULT NULL,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL
    )");
    echo "Created on_call tables\n";
}

echo "Done.\n";

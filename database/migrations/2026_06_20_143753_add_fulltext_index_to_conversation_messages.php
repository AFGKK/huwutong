<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (! Schema::hasTable('conversation_messages') || in_array($driver, ['sqlite'])) {
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("CREATE INDEX IF NOT EXISTS messages_content_fulltext ON conversation_messages USING GIN(to_tsvector('simple', content))");
            return;
        }

        $indexExists = collect(DB::select("SHOW INDEX FROM conversation_messages WHERE Key_name = 'messages_content_fulltext'"))->isNotEmpty();
        if (! $indexExists) {
            DB::statement('ALTER TABLE conversation_messages ADD FULLTEXT INDEX messages_content_fulltext (content)');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (! Schema::hasTable('conversation_messages') || in_array($driver, ['sqlite'])) {
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS messages_content_fulltext');
            return;
        }

        DB::statement('ALTER TABLE conversation_messages DROP INDEX messages_content_fulltext');
    }
};

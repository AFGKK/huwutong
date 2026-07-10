<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (Schema::hasTable('conversation_messages')) {
            DB::statement('DROP INDEX IF EXISTS messages_content_fulltext');
            DB::statement('DROP INDEX IF EXISTS msg_content_ft');
            DB::statement('DROP INDEX IF EXISTS conversation_messages_content_fts');

            DB::statement("
                CREATE INDEX conversation_messages_content_fts
                ON conversation_messages
                USING GIN (to_tsvector('simple', coalesce(content, '')))
                WHERE deleted_at IS NULL
                  AND content IS NOT NULL
                  AND content <> ''
            ");
        }

        if (Schema::hasTable('kb_articles')) {
            DB::statement('DROP INDEX IF EXISTS kb_articles_fulltext');
            DB::statement('DROP INDEX IF EXISTS kb_articles_fulltext_fts');

            DB::statement("
                CREATE INDEX kb_articles_fulltext_fts
                ON kb_articles
                USING GIN (to_tsvector('simple', coalesce(title, '') || ' ' || coalesce(content, '')))
                WHERE deleted_at IS NULL
            ");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS conversation_messages_content_fts');
        DB::statement('DROP INDEX IF EXISTS kb_articles_fulltext_fts');
    }
};

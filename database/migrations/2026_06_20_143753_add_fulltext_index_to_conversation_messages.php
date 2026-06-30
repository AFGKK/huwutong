<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_messages') || Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $indexExists = collect(DB::select("SHOW INDEX FROM conversation_messages WHERE Key_name = 'messages_content_fulltext'"))->isNotEmpty();
        if (! $indexExists) {
            DB::statement('ALTER TABLE conversation_messages ADD FULLTEXT INDEX messages_content_fulltext (content)');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('conversation_messages') || Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE conversation_messages DROP INDEX messages_content_fulltext');
    }
};

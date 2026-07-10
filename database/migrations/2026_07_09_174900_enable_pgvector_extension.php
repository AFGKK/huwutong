<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $available = DB::selectOne(
            "SELECT COUNT(*)::int AS cnt FROM pg_available_extensions WHERE name = 'vector'"
        );

        if (($available->cnt ?? 0) === 0) {
            Log::warning('pgvector extension not available on this PostgreSQL server; skip. Run: php scripts/install-pgvector.php after installing pgvector binaries.');

            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $installed = DB::selectOne(
            "SELECT COUNT(*)::int AS cnt FROM pg_extension WHERE extname = 'vector'"
        );

        if (($installed->cnt ?? 0) > 0) {
            DB::statement('DROP EXTENSION IF EXISTS vector');
        }
    }
};

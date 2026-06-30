<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_participants')) {
            return;
        }

        Schema::table('conversation_participants', function (Blueprint $table) {
            if (! Schema::hasColumn('conversation_participants', 'is_hidden')) {
                $table->boolean('is_hidden')->default(false)->after('archived_at')->comment('是否隐藏');
            }
            if (! Schema::hasColumn('conversation_participants', 'hidden_at')) {
                $table->timestamp('hidden_at')->nullable()->after('is_hidden')->comment('隐藏时间');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('conversation_participants')) {
            return;
        }

        Schema::table('conversation_participants', function (Blueprint $table) {
            $columns = array_filter(
                ['is_hidden', 'hidden_at'],
                fn (string $column) => Schema::hasColumn('conversation_participants', $column),
            );
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

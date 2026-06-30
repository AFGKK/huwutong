<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('withdrawals', 'user_id')) {
            Schema::table('withdrawals', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('earnings_account_id')->constrained('users');
                $table->index('user_id');
            });
        }

        // Fix: make created_by nullable on payout_batches (was NOT NULL in original migration)
        // SQLite does not support ALTER COLUMN, skip check for test env
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('payout_batches', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('withdrawals', 'user_id')) {
            Schema::table('withdrawals', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }

        Schema::table('payout_batches', function (Blueprint $table) {
            // Cannot revert nullable easily; no-op
        });
    }
};

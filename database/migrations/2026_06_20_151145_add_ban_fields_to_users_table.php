<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'banned_at')) {
                $table->timestamp('banned_at')->nullable()->after('locked_until')->comment('封禁时间');
            }
            if (!Schema::hasColumn('users', 'banned_reason')) {
                $table->string('banned_reason', 500)->nullable()->after('banned_at')->comment('封禁原因');
            }
            if (!Schema::hasColumn('users', 'banned_by')) {
                $table->foreignId('banned_by')->nullable()->after('banned_reason')->constrained('users')->nullOnDelete()->comment('封禁执行人');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['banned_at', 'banned_reason', 'banned_by']);
        });
    }
};

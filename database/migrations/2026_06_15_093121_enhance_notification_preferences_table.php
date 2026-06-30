<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            if (!Schema::hasColumn('notification_preferences', 'quiet_hours_start')) {
                $table->string('quiet_hours_start', 5)->nullable()->after('types')
                    ->comment('免打扰开始时间 (HH:mm)');
            }
            if (!Schema::hasColumn('notification_preferences', 'quiet_hours_end')) {
                $table->string('quiet_hours_end', 5)->nullable()->after('quiet_hours_start')
                    ->comment('免打扰结束时间 (HH:mm)');
            }
            if (!Schema::hasColumn('notification_preferences', 'timezone')) {
                $table->string('timezone', 64)->default('Asia/Shanghai')->after('quiet_hours_end')
                    ->comment('用户时区');
            }
            if (!Schema::hasColumn('notification_preferences', 'digest_frequency')) {
                $table->string('digest_frequency', 20)->default('none')->after('timezone')
                    ->comment('摘要频率: none|daily|weekly|monthly');
            }
            if (!Schema::hasColumn('notification_preferences', 'last_digest_sent_at')) {
                $table->timestamp('last_digest_sent_at')->nullable()->after('digest_frequency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $columns = ['quiet_hours_start', 'quiet_hours_end', 'timezone', 'digest_frequency', 'last_digest_sent_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('notification_preferences', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

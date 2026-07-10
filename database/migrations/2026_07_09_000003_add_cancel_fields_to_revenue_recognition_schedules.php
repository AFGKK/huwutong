<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revenue_recognition_schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('revenue_recognition_schedules', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('revenue_recognition_schedules', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('cancelled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('revenue_recognition_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('revenue_recognition_schedules', 'cancel_reason')) {
                $table->dropColumn('cancel_reason');
            }
            if (Schema::hasColumn('revenue_recognition_schedules', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('affiliate_creatives', 'created_by')) { return; }
        Schema::table('affiliate_creatives', function (Blueprint $table) {
            $table->string('status', 20)->default('approved')->after('is_active')
                ->comment('pending/approved/rejected');
            $table->foreignId('created_by')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('created_by');
            $table->string('review_notes')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_creatives', function (Blueprint $table) {
            $table->dropColumn(['status', 'created_by', 'reviewed_at', 'review_notes']);
        });
    }
};

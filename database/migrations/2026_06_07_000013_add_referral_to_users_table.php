<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code', 20)->unique()->nullable()->after('phone_verified_at');
            }
            if (! Schema::hasColumn('users', 'referred_by')) {
                $table->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete()->after('referral_code');
            }
            if (! Schema::hasColumn('users', 'commission_balance')) {
                $table->decimal('commission_balance', 12, 2)->default(0)->after('referred_by');
            }
            if (! Schema::hasColumn('users', 'total_commission_earned')) {
                $table->decimal('total_commission_earned', 12, 2)->default(0)->after('commission_balance');
            }
            if (! Schema::hasColumn('users', 'agent_id')) {
                $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete()->after('total_commission_earned');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'referral_code',
                'referred_by',
                'commission_balance',
                'total_commission_earned',
                'agent_id',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // marketplace_developers 追加收益账户关联
        if (Schema::hasTable('marketplace_developers')) {
            Schema::table('marketplace_developers', function (Blueprint $table) {
                if (!Schema::hasColumn('marketplace_developers', 'earnings_account_id')) {
                    $table->foreignId('earnings_account_id')->nullable()->after('verified_by')
                          ->constrained('earnings_accounts')->nullOnDelete();
                }
                if (!Schema::hasColumn('marketplace_developers', 'commission_rate')) {
                    $table->decimal('commission_rate', 5, 2)->default(80)->after('earnings_account_id');
                }
                if (!Schema::hasColumn('marketplace_developers', 'total_earned')) {
                    $table->decimal('total_earned', 14, 2)->default(0)->after('commission_rate');
                }
                if (!Schema::hasColumn('marketplace_developers', 'total_withdrawn')) {
                    $table->decimal('total_withdrawn', 14, 2)->default(0)->after('total_earned');
                }
                if (!Schema::hasColumn('marketplace_developers', 'tax_id')) {
                    $table->string('tax_id')->nullable()->after('total_withdrawn');
                }
                if (!Schema::hasColumn('marketplace_developers', 'tax_info')) {
                    $table->json('tax_info')->nullable()->after('tax_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_developers')) {
            Schema::table('marketplace_developers', function (Blueprint $table) {
                $table->dropColumn(['earnings_account_id', 'commission_rate', 'total_earned', 'total_withdrawn', 'tax_id', 'tax_info']);
            });
        }
    }
};

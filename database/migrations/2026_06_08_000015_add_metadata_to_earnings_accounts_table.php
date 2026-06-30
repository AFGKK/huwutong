<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('earnings_accounts', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('status')
                ->comment('扩展元数据（负余额记录、风控标记等）');
        });
    }

    public function down(): void
    {
        Schema::table('earnings_accounts', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};

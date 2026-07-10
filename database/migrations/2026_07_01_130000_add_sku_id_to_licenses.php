<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('licenses', 'sku_id')) { return; }
        Schema::table('licenses', function (Blueprint $table) {
            $table->foreignId('sku_id')->nullable()->after('product_id')
                ->constrained('product_skus')->nullOnDelete();
        });

        // 回填已有数据的 sku_id（从 metadata JSON 中读取）
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("UPDATE licenses SET sku_id = (metadata->>'sku_id')::bigint WHERE metadata->>'sku_id' IS NOT NULL");
        } elseif ($driver === 'sqlite') {
            DB::statement("UPDATE licenses SET sku_id = json_extract(metadata, '$.sku_id') WHERE json_extract(metadata, '$.sku_id') IS NOT NULL");
        } else {
            DB::statement("UPDATE licenses SET sku_id = JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.sku_id')) WHERE JSON_EXTRACT(metadata, '$.sku_id') IS NOT NULL");
        }
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropForeign(['sku_id']);
            $table->dropColumn('sku_id');
        });
    }
};

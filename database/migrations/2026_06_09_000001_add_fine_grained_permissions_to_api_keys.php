<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('api_keys', 'endpoint_permissions')) {
            Schema::table('api_keys', function (Blueprint $table) {
                // 端点级细粒度权限: { "activate": ["GET","POST"], "validate": ["GET"] }
                $table->json('endpoint_permissions')->nullable()->after('allowed_methods')
                    ->comment('端点级方法权限: {"端点名": ["GET","POST",...]}');
            });
        }
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn('endpoint_permissions');
        });
    }
};

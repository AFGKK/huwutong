<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('remember_tenant_id')->nullable()->after('tenant_id')
                ->comment('记住的上次选择的租户（多租户用户快速切换）');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('slug', 100)->nullable()->unique()->after('name')
                ->comment('租户 URL 标识（用于多租户选择页展示）');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('remember_tenant_id');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};

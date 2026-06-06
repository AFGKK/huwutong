<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('is_sandbox')->default(false)->after('data_region')->comment('是否为沙箱环境');
            $table->timestamp('sandbox_expires_at')->nullable()->after('is_sandbox')->comment('沙箱过期时间');
        });

        Schema::create('sandbox_products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Sandbox Product')->comment('沙箱产品名');
            $table->string('slug')->unique()->comment('产品标识');
            $table->text('description')->nullable();
            $table->string('version')->default('1.0.0');
            $table->boolean('is_active')->default(true);
            $table->json('modules')->nullable()->comment('模块列表');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sandbox_products');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['is_sandbox', 'sandbox_expires_at']);
        });
    }
};

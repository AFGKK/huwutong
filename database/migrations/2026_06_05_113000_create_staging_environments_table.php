<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('has_staging')->default(false)->after('sandbox_expires_at');
        });

        Schema::create('staging_environments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('默认 Staging 环境');
            $table->string('subdomain')->unique()->nullable()->comment('staging 子域名');
            $table->string('status')->default('inactive')->comment('inactive/active/suspended');
            $table->string('api_base_url')->nullable()->comment('staging API 地址');
            $table->integer('rate_limit')->default(120)->comment('API 限速请求/分钟');
            $table->json('config')->nullable()->comment('staging 环境配置');
            $table->timestamp('last_reset_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staging_environments');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('has_staging');
        });
    }
};

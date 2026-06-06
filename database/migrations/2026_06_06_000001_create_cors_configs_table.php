<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cors_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('配置名称');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->json('allowed_origins')->comment('允许的 Origin（支持通配符 *）');
            $table->json('allowed_methods')->default('["GET","POST","PUT","PATCH","DELETE","OPTIONS"]');
            $table->json('allowed_headers')->default('["Content-Type","Authorization","X-Requested-With","X-Api-Key","X-License-Key","X-Tenant-Id","X-Idempotency-Key","X-Nonce","X-Signature"]');
            $table->json('exposed_headers')->default('["X-RateLimit-Limit","X-RateLimit-Remaining","X-RateLimit-Reset","X-Request-Id"]');
            $table->boolean('allow_credentials')->default(true);
            $table->unsignedInteger('max_age')->default(86400)->comment('预检缓存秒数');
            $table->string('route_pattern')->nullable()->comment('路由匹配模式（如 api/*）');
            $table->unsignedSmallInteger('priority')->default(0)->comment('优先级，数字越大越优先');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cors_configs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('license_key')->unique()->comment('License Key');
            $table->string('type')->default('standard')->comment('类型: trial/standard/enterprise');
            $table->string('status')->default('pending')->comment('状态: pending/active/suspended/frozen/expired/revoked/refunded/blacklisted');
            $table->timestamp('activated_at')->nullable()->comment('激活时间');
            $table->timestamp('expires_at')->nullable()->comment('到期时间');
            $table->integer('seats')->default(1)->comment('席位数量');
            $table->integer('max_devices')->default(1)->comment('最大设备数');
            $table->json('metadata')->nullable()->comment('扩展元数据');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index('license_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};

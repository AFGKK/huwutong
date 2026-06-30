<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_sku_id')->constrained('product_skus')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->nullable()->comment('订阅邮箱（未登录时）');
            $table->string('phone')->nullable()->comment('订阅手机号');
            $table->boolean('notified')->default(false)->comment('是否已通知');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['product_sku_id', 'email']);
            $table->index('notified');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_notifications');
    }
};

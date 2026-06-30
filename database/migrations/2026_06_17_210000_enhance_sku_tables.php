<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 库存变更日志表
        Schema::create('sku_stock_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_sku_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('change')->comment('变更数量（正=入库，负=出库）');
            $table->integer('old_stock')->comment('变更前库存');
            $table->integer('new_stock')->comment('变更后库存');
            $table->string('reason', 255)->nullable()->comment('变更原因');
            $table->string('reference_type', 50)->nullable()->comment('关联类型: order/return/adjustment');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('关联ID');
            $table->timestamps();

            $table->index(['product_sku_id', 'created_at']);
        });

        // 为 product_skus 表添加新字段
        Schema::table('product_skus', function (Blueprint $table) {
            if (!Schema::hasColumn('product_skus', 'low_stock_threshold')) {
                $table->integer('low_stock_threshold')->default(10)->after('commission_rate');
            }
            if (!Schema::hasColumn('product_skus', 'allow_backorder')) {
                $table->boolean('allow_backorder')->default(false)->after('low_stock_threshold');
            }
            if (!Schema::hasColumn('product_skus', 'weight')) {
                $table->decimal('weight', 10, 2)->nullable()->after('allow_backorder')->comment('重量(kg)');
            }
            if (!Schema::hasColumn('product_skus', 'length')) {
                $table->decimal('length', 10, 2)->nullable()->after('weight')->comment('长(cm)');
            }
            if (!Schema::hasColumn('product_skus', 'width')) {
                $table->decimal('width', 10, 2)->nullable()->after('length')->comment('宽(cm)');
            }
            if (!Schema::hasColumn('product_skus', 'height')) {
                $table->decimal('height', 10, 2)->nullable()->after('width')->comment('高(cm)');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_stock_logs');

        Schema::table('product_skus', function (Blueprint $table) {
            $table->dropColumn([
                'low_stock_threshold', 'allow_backorder',
                'weight', 'length', 'width', 'height',
            ]);
        });
    }
};

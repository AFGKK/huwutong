<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 给 products 表添加演示相关字段
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'demo_enabled')) {
                $table->boolean('demo_enabled')->default(false)->after('view_count')->comment('是否启用演示');
            }
            if (!Schema::hasColumn('products', 'demo_images')) {
                $table->json('demo_images')->nullable()->after('demo_enabled')->comment('演示图片数组，如：[{"label":"H5移动端演示","url":"..."},{"label":"微信小程序","url":"..."}]');
            }
        });

        // 2. 清理旧字段（如果存在）
        Schema::table('products', function (Blueprint $table) {
            $dropOld = ['demo_qr_h5', 'demo_qr_miniapp'];
            foreach ($dropOld as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // 3. 创建 product_demos 表
        Schema::create('product_demos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete()->comment('所属产品');
            $table->string('platform')->comment('演示平台，如：管理后台 / PC端前台 / H5端前台');
            $table->string('site_url')->nullable()->comment('演示站点URL');
            $table->string('account')->nullable()->comment('演示账号');
            $table->string('password')->nullable()->comment('演示密码');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_demos');

        Schema::table('products', function (Blueprint $table) {
            $drop = ['demo_enabled', 'demo_images'];
            foreach ($drop as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

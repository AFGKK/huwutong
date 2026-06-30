<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 规格组
        Schema::create('product_spec_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100)->comment('规格组名: 如 基本参数/性能/网络');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 规格项
        Schema::create('product_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spec_group_id')->constrained('product_spec_groups')->cascadeOnDelete();
            $table->string('label', 100)->comment('规格标签: 如 CPU/内存/存储');
            $table->string('type', 20)->default('text')->comment('text/number/boolean/select');
            $table->string('unit', 30)->nullable()->comment('单位: GHz/GB/核心');
            $table->text('options')->nullable()->comment('select类型的选项 JSON');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 规格值
        Schema::create('product_spec_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spec_id')->constrained('product_specs')->cascadeOnDelete();
            $table->string('value', 500)->nullable()->comment('规格值');
            $table->timestamps();

            $table->unique(['product_id', 'spec_id']);
        });

        // 商品比较列表（用户保存的比较）
        Schema::create('product_comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 100)->nullable()->comment('游客会话ID');
            $table->string('name', 100)->nullable()->comment('对比列表名称');
            $table->timestamps();
        });

        // 比较列表中的商品
        Schema::create('product_comparison_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comparison_id')->constrained('product_comparisons')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['comparison_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_comparison_items');
        Schema::dropIfExists('product_comparisons');
        Schema::dropIfExists('product_spec_values');
        Schema::dropIfExists('product_specs');
        Schema::dropIfExists('product_spec_groups');
    }
};

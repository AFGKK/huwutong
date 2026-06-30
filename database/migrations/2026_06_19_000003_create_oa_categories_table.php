<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // OA 分类表
        Schema::create('oa_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('分类名称');
            $table->string('icon', 50)->nullable()->comment('分类图标 emoji');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 给 official_accounts 加 category_id
        if (!Schema::hasColumn('official_accounts', 'category_id')) {
            Schema::table('official_accounts', function (Blueprint $table) {
                $table->foreignId('category_id')->nullable()->after('owner_id')
                    ->constrained('oa_categories')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('official_accounts', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
        Schema::dropIfExists('oa_categories');
    }
};

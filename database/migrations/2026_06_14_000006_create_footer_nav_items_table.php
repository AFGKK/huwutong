<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('footer_nav_items')) {
            return;
        }
        if (!Schema::hasTable('footer_nav_items')) {
            Schema::create('footer_nav_items', function (Blueprint $table) {
                $table->id();
                $table->string('label', 100)->comment('显示名称');
                $table->string('type', 30)->default('custom')->comment('page/custom/help/api_docs/status/social/contact');
                $table->string('url', 500)->nullable()->comment('链接URL');
                $table->string('icon', 100)->nullable()->comment('图标');
                $table->string('target', 20)->default('_self')->comment('_self/_blank');
                $table->integer('sort_order')->default(0)->comment('排序');
                $table->boolean('is_active')->default(true);
                $table->string('group', 30)->default('footer')->comment('footer/social/bottom');
                $table->timestamps();

                $table->index(['is_active', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('footer_nav_items');
    }
};

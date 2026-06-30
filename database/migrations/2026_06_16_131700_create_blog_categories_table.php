<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('blog_categories')) {
            Schema::create('blog_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->comment('分类名称');
                $table->string('slug', 120)->unique()->comment('URL标识');
                $table->text('description')->nullable()->comment('描述');
                $table->string('color', 30)->nullable()->comment('显示颜色');
                $table->integer('sort_order')->default(0)->comment('排序');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('blog_posts', 'category_id')) {
            Schema::table('blog_posts', function (Blueprint $table) {
                $table->foreignId('category_id')->nullable()->after('type')
                    ->constrained('blog_categories')->nullOnDelete();
                $table->index('category_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('blog_posts', 'category_id')) {
            Schema::table('blog_posts', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            });
        }
        Schema::dropIfExists('blog_categories');
    }
};

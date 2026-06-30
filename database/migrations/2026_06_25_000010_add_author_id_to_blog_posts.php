<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('blog_posts', 'author_id')) {
            return;
        }

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable()->after('author')->constrained('users')->nullOnDelete();
        });

        // 将已有的 author 名称匹配到 user 记录
        $admin = DB::table('users')->where('name', '超级管理员')->first();
        if ($admin) {
            DB::table('blog_posts')->whereNull('author_id')->update(['author_id' => $admin->id]);
        }
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->dropColumn('author_id');
        });
    }
};

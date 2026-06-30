<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        \App\Models\ForumCategory::insert([
            ['name' => '闲聊', 'icon' => '💬', 'sort_order' => 1],
            ['name' => '分享', 'icon' => '📤', 'sort_order' => 2],
            ['name' => '问答', 'icon' => '❓', 'sort_order' => 3],
            ['name' => '资源', 'icon' => '📦', 'sort_order' => 4],
            ['name' => '求助', 'icon' => '🆘', 'sort_order' => 5],
        ]);
    }

    public function down(): void
    {
        \App\Models\ForumCategory::whereIn('name', ['闲聊', '分享', '问答', '资源', '求助'])->delete();
    }
};

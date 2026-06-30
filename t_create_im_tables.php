<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// 1. user_conversations - 核心会话表
if (!Schema::hasTable('user_conversations')) {
    Schema::create('user_conversations', function (Blueprint $t) {
        $t->id();
        $t->string('type', 20)->default('single')->index(); // single/group/ai
        $t->string('name', 200)->nullable();
        $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $t->unsignedBigInteger('last_message_id')->nullable();
        $t->timestamp('last_message_at')->nullable();
        $t->unsignedInteger('slow_mode_interval')->default(0);
        $t->boolean('join_approval')->default(false);
        $t->json('permissions')->nullable();
        $t->timestamps();
        $t->softDeletes();
        $t->index(['type', 'deleted_at']);
    });
    echo "Created: user_conversations\n";
} else echo "Exists: user_conversations\n";

// 2. conversation_participants - 会话参与者
if (!Schema::hasTable('conversation_participants')) {
    Schema::create('conversation_participants', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('conversation_id');
        $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $t->string('role', 20)->default('member'); // creator/admin/member
        $t->timestamp('last_read_at')->nullable();
        $t->boolean('is_muted')->default(false);
        $t->timestamp('muted_until')->nullable();
        $t->timestamp('archived_at')->nullable();
        $t->boolean('is_hidden')->default(false);
        $t->string('nickname', 100)->nullable();
        $t->timestamps();
        $t->softDeletes();
        $t->unique(['conversation_id', 'user_id']);
    });
    echo "Created: conversation_participants\n";
} else echo "Exists: conversation_participants\n";

// 3. user_friends - 好友关系
if (!Schema::hasTable('user_friends')) {
    Schema::create('user_friends', function (Blueprint $t) {
        $t->id();
        $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $t->foreignId('friend_id')->constrained('users')->cascadeOnDelete();
        $t->unsignedBigInteger('group_id')->nullable();
        $t->string('remark', 100)->nullable();
        $t->string('status', 20)->default('pending'); // pending/accepted/blocked
        $t->timestamp('accepted_at')->nullable();
        $t->timestamps();
        $t->unique(['user_id', 'friend_id']);
    });
    echo "Created: user_friends\n";
} else echo "Exists: user_friends\n";

// 4. friend_groups - 好友分组
if (!Schema::hasTable('friend_groups')) {
    Schema::create('friend_groups', function (Blueprint $t) {
        $t->id();
        $t->foreignId('user_id')->constrained()->cascadeOnDelete();
        $t->string('name', 100);
        $t->unsignedInteger('sort_order')->default(0);
        $t->timestamps();
    });
    echo "Created: friend_groups\n";
} else echo "Exists: friend_groups\n";

// 5. message_favorites - 消息收藏
if (!Schema::hasTable('message_favorites')) {
    Schema::create('message_favorites', function (Blueprint $t) {
        $t->id();
        $t->foreignId('user_id')->constrained()->cascadeOnDelete();
        $t->unsignedBigInteger('message_id');
        $t->unsignedBigInteger('conversation_id');
        $t->timestamps();
        $t->unique(['user_id', 'message_id']);
    });
    echo "Created: message_favorites\n";
} else echo "Exists: message_favorites\n";

echo "\nDone!\n";

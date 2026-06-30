<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

$fixed = 0;

// 1. user_friends - add addressee_id
if (Schema::hasTable('user_friends') && !Schema::hasColumn('user_friends', 'addressee_id')) {
    Schema::table('user_friends', function (Blueprint $t) {
        $t->foreignId('addressee_id')->nullable()->constrained('users')->nullOnDelete()->after('friend_id');
    });
    echo "✅ Added addressee_id to user_friends\n"; $fixed++;
} else echo "✅ user_friends.addressee_id exists\n";

// 2. conversation_participants - add is_pinned
if (Schema::hasTable('conversation_participants') && !Schema::hasColumn('conversation_participants', 'is_pinned')) {
    Schema::table('conversation_participants', function (Blueprint $t) {
        $t->boolean('is_pinned')->default(false)->after('is_muted');
    });
    echo "✅ Added is_pinned to conversation_participants\n"; $fixed++;
} else echo "✅ conversation_participants.is_pinned exists\n";

// 3. conversation_messages - add deleted_at (soft delete)
if (Schema::hasTable('conversation_messages') && !Schema::hasColumn('conversation_messages', 'deleted_at')) {
    Schema::table('conversation_messages', function (Blueprint $t) {
        $t->timestamp('deleted_at')->nullable()->after('updated_at');
    });
    echo "✅ Added deleted_at to conversation_messages\n"; $fixed++;
} else echo "✅ conversation_messages.deleted_at exists\n";

// 4. ai_friend_profiles - add published_at
if (Schema::hasTable('ai_friend_profiles') && !Schema::hasColumn('ai_friend_profiles', 'published_at')) {
    Schema::table('ai_friend_profiles', function (Blueprint $t) {
        $t->timestamp('published_at')->nullable()->after('welcome_message');
    });
    echo "✅ Added published_at to ai_friend_profiles\n"; $fixed++;
} else echo "✅ ai_friend_profiles.published_at exists\n";

// 5. conversation_messages - add other commonly used columns
foreach ([
    ['table' => 'conversation_messages', 'column' => 'message_type', 'type' => "string", 'args' => [30, 'default' => 'text']],
    ['table' => 'conversation_messages', 'column' => 'metadata', 'type' => 'json', 'args' => ['nullable' => true]],
] as $c) {
    if (Schema::hasTable($c['table']) && !Schema::hasColumn($c['table'], $c['column'])) {
        Schema::table($c['table'], function (Blueprint $t) use ($c) {
            $method = $c['type'];
            $col = $t->$method($c['column'], ...($c['args'] ?? []));
            if (isset($c['args']['default'])) $col->default($c['args']['default']);
            if (isset($c['args']['nullable'])) $col->nullable();
        });
        echo "✅ Added {$c['column']} to {$c['table']}\n"; $fixed++;
    } else echo "✅ {$c['table']}.{$c['column']} exists\n";
}

echo "\nFixed {$fixed} issues.\n";

<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;

$fixes = [
    ['table' => 'user_friends', 'column' => 'requester_id', 'sql' => 'ALTER TABLE user_friends ADD COLUMN requester_id BIGINT UNSIGNED NULL AFTER friend_id'],
    ['table' => 'user_friends', 'column' => 'friend_group_id', 'sql' => 'ALTER TABLE user_friends ADD COLUMN friend_group_id BIGINT UNSIGNED NULL AFTER requester_id'],
    ['table' => 'conversation_participants', 'column' => 'last_message_id', 'sql' => 'ALTER TABLE conversation_participants ADD COLUMN last_message_id BIGINT UNSIGNED NULL AFTER is_hidden'],
    ['table' => 'conversation_participants', 'column' => 'notify_level', 'sql' => "ALTER TABLE conversation_participants ADD COLUMN notify_level VARCHAR(20) DEFAULT 'all' AFTER last_message_id"],
];

foreach ($fixes as $f) {
    if (!Schema::hasColumn($f['table'], $f['column'])) {
        \Illuminate\Support\Facades\DB::statement($f['sql']);
        echo "✅ Added {$f['column']} to {$f['table']}\n";
    } else {
        echo "✅ {$f['table']}.{$f['column']} exists\n";
    }
}

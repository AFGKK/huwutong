<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

$fixes = [
    ['table' => 'user_friends', 'column' => 'requester_id', 'type' => 'unsignedBigInteger', 'nullable' => true],
    ['table' => 'user_friends', 'column' => 'friend_group_id', 'type' => 'unsignedBigInteger', 'nullable' => true],
    ['table' => 'conversation_participants', 'column' => 'last_message_id', 'type' => 'unsignedBigInteger', 'nullable' => true],
    ['table' => 'conversation_participants', 'column' => 'notify_level', 'type' => 'string', 'length' => 20, 'default' => 'all'],
];

foreach ($fixes as $f) {
    if (!Schema::hasColumn($f['table'], $f['column'])) {
        Schema::table($f['table'], function (Blueprint $t) use ($f) {
            $col = $t->{$f['type']}($f['column'], $f['length'] ?? 150);
            if (isset($f['default'])) $col->default($f['default']);
            if (!empty($f['nullable'])) $col->nullable();
        });
        echo "✅ Added {$f['column']} to {$f['table']}\n";
    } else {
        echo "✅ {$f['table']}.{$f['column']} exists\n";
    }
}

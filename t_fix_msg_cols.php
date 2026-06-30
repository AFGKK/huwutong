<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;

$fixes = [
    ['table' => 'conversation_messages', 'column' => 'client_msg_id', 'sql' => "ALTER TABLE conversation_messages ADD COLUMN client_msg_id VARCHAR(100) NULL AFTER id"],
    ['table' => 'conversation_messages', 'column' => 'sender_type', 'sql' => "ALTER TABLE conversation_messages ADD COLUMN sender_type VARCHAR(20) DEFAULT 'user' AFTER sender_id"],
];

foreach ($fixes as $f) {
    if (!Schema::hasColumn($f['table'], $f['column'])) {
        \Illuminate\Support\Facades\DB::statement($f['sql']);
        echo "✅ Added {$f['column']} to {$f['table']}\n";
    } else {
        echo "✅ {$f['table']}.{$f['column']} exists\n";
    }
}

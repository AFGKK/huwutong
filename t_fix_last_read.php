<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;

$fixes = [
    ['table' => 'user_conversations', 'column' => 'last_read_at', 'sql' => "ALTER TABLE user_conversations ADD COLUMN last_read_at TIMESTAMP NULL AFTER last_message_at"],
];

foreach ($fixes as $f) {
    if (!Schema::hasColumn($f['table'], $f['column'])) {
        \Illuminate\Support\Facades\DB::statement($f['sql']);
        echo "✅ Added {$f['column']} to {$f['table']}\n";
    } else {
        echo "✅ {$f['table']}.{$f['column']} exists\n";
    }
}

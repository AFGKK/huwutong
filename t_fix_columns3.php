<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

$fixes = [
    ['table' => 'conversation_participants', 'column' => 'unread_count', 'type' => 'unsignedInteger', 'default' => 0],
    ['table' => 'conversation_messages', 'column' => 'message_type', 'type' => 'string', 'length' => 30, 'default' => 'text'],
    ['table' => 'conversation_messages', 'column' => 'metadata', 'type' => 'json', 'nullable' => true],
];

foreach ($fixes as $f) {
    if (!Schema::hasColumn($f['table'], $f['column'])) {
        Schema::table($f['table'], function (Blueprint $t) use ($f) {
            $col = $t->{$f['type']}($f['column'], $f['length'] ?? null);
            if (isset($f['default'])) $col->default($f['default']);
            if (!empty($f['nullable'])) $col->nullable();
        });
        echo "✅ Added {$f['column']} to {$f['table']}\n";
    } else {
        echo "✅ {$f['table']}.{$f['column']} exists\n";
    }
}

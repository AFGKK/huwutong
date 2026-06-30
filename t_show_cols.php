<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Show the conversation_messages columns
$cols = \Illuminate\Support\Facades\DB::select('SHOW COLUMNS FROM conversation_messages');
echo "Columns in conversation_messages:\n";
foreach ($cols as $c) {
    echo "  {$c->Field} ({$c->Type})\n";
}

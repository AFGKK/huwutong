<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$friends = \Illuminate\Support\Facades\DB::table('ai_friend_profiles')->get();
echo "AI friends: " . $friends->count() . "\n";
foreach ($friends as $f) {
    echo "  - {$f->name} (" . substr($f->welcome_message ?? '', 0, 30) . ")\n";
}

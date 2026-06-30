<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== AI 好友数据 ===\n";
$friends = \Illuminate\Support\Facades\DB::table('ai_friend_profiles')->get();
echo "Count: " . $friends->count() . "\n";
foreach ($friends as $f) {
    echo "  - {$f->name} ({$f->category})\n";
}

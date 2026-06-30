<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$users = \Illuminate\Support\Facades\DB::table('users')->where('user_type', 'ai_friend')->get();
echo "AI friend users: " . $users->count() . "\n";
foreach ($users as $u) {
    echo "  - {$u->name} ({$u->email})\n";
}
$friends = \Illuminate\Support\Facades\DB::table('ai_friend_profiles')->get();
echo "\nAI friend profiles: " . $friends->count() . "\n";
foreach ($friends as $f) {
    echo "  - profile id={$f->id}, user_id={$f->user_id}, visibility={$f->visibility}\n";
}

<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$members = \Illuminate\Support\Facades\DB::table('channel_members')->where('channel_id', 1)->get();
echo "Channel 1 members:\n";
foreach ($members as $m) {
    echo "  user_id={$m->user_id}, role={$m->role}\n";
}

$channel = \Illuminate\Support\Facades\DB::table('channels')->where('id', 1)->first();
echo "\nChannel: {$channel->name}\n";

<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$tables = ['conversation_messages','live_chat_conversations','live_chat_messages','conversation_participants','conversations','friends','friend_groups','friend_requests','user_online_statuses'];
foreach ($tables as $t) {
    echo "$t: " . (\Illuminate\Support\Facades\Schema::hasTable($t) ? 'Y' : 'N') . "\n";
}

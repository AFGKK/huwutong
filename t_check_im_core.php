<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

foreach (['user_conversations','conversation_messages','conversation_participants','user_friends','friend_groups','message_favorites'] as $t) {
    echo "$t: " . (\Illuminate\Support\Facades\Schema::hasTable($t) ? 'Y' : 'N') . "\n";
}

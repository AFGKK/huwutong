<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $msgs = App\Models\ConversationMessage::with('sender:id,name')
        ->where('conversation_id', 5)
        ->get();
    echo "OK: " . $msgs->count() . " messages\n";
    foreach ($msgs as $m) {
        echo "  msg_id={$m->id} type={$m->message_type} content=" . mb_substr($m->content, 0, 50) . "\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

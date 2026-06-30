<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use App\Models\ConversationMessage;

// Check all messages in conv 5 with thread data
$msgs = ConversationMessage::where('conversation_id', 5)
    ->where(function($q) {
        $q->whereNotNull('thread_parent_id')->orWhere('thread_reply_count', '>', 0);
    })->get();

echo "Found " . $msgs->count() . " thread-related messages:\n";
foreach ($msgs as $m) {
    echo "ID={$m->id}: content='{$m->content}' parent_id={$m->thread_parent_id} replies={$m->thread_reply_count}\n";
}

// Also show last 5 messages
echo "\nLast 5 messages in conv 5:\n";
$last5 = ConversationMessage::where('conversation_id', 5)->orderBy('id', 'desc')->limit(5)->get();
foreach ($last5 as $m) {
    echo "ID={$m->id}: content='{$m->content}' type={$m->message_type}\n";
}

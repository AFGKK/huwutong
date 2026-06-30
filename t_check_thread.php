<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use App\Models\ConversationMessage;
use App\Models\UserConversation;
use App\Models\ConversationParticipant;

// Find conversations for user 1
$parts = ConversationParticipant::where('user_id', 1)->get();
echo "User 1 conversations:\n";
foreach ($parts as $p) {
    $conv = UserConversation::find($p->conversation_id);
    $count = ConversationMessage::where('conversation_id', $p->conversation_id)->count();
    echo "  Conv {$p->conversation_id}: '{$conv->name}' ({$conv->type}) - {$count} msgs\n";
}

// Check thread test data
$threadMsgs = ConversationMessage::where('thread_parent_id', '>', 0)->get();
echo "\nThread replies: {$threadMsgs->count()}\n";
$rootMsgs = ConversationMessage::where('thread_reply_count', '>', 0)->get();
echo "Root messages with replies: {$rootMsgs->count()}\n";
foreach ($rootMsgs as $m) {
    echo "  Msg {$m->id} in conv {$m->conversation_id}: '{$m->content}' - {$m->thread_reply_count} replies\n";
}

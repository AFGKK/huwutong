<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use App\Models\ConversationMessage;

// Check the thread replies directly
$msgs = ConversationMessage::whereIn('id', [42,43,44,45])->get();
foreach ($msgs as $m) {
    echo "Msg {$m->id}: content='{$m->content}' thread_parent_id={$m->thread_parent_id} thread_reply_count={$m->thread_reply_count}\n";
}

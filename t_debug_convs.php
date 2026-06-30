<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserConversation;

// Direct query: what does the API return for user 1?
$convs = UserConversation::whereHas('participants', function($q) {
    $q->where('user_id', 1)->whereNull('deleted_at');
})->get(['id', 'name', 'type', 'created_by']);

echo "API would return " . $convs->count() . " conversations:\n";
foreach ($convs as $c) {
    echo "ID={$c->id} name='{$c->name}' type={$c->type}\n";
}

// Check if conv 1 has any messages that would show
$msgCount = \App\Models\ConversationMessage::where('conversation_id', 1)->count();
echo "\nConv 1 has {$msgCount} messages\n";
echo "Conv 1 participants: ";
$participants = \App\Models\ConversationParticipant::where('conversation_id', 1)->get();
foreach ($participants as $p) {
    echo "user_id={$p->user_id} ";
}
echo "\n";

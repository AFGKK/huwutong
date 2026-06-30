<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserConversation;
use App\Models\ConversationMessage;
use App\Models\User;

// Find a conversation
$convs = UserConversation::limit(3)->get();
echo "=== Conversations ===\n";
foreach ($convs as $c) {
    echo "ID: {$c->id}, Name: {$c->name}, Type: {$c->type}\n";
}

// Find first user (admin)
$user = User::first();
echo "\n=== User ===\n";
echo "ID: {$user->id}, Name: {$user->name}, Email: {$user->email}\n";

// If no conversation exists, create one (self-chat)
if ($convs->isEmpty()) {
    $conv = UserConversation::create([
        'name' => '测试会话',
        'type' => 'private',
        'created_by' => $user->id,
    ]);
    \App\Models\ConversationParticipant::create([
        'conversation_id' => $conv->id,
        'user_id' => $user->id,
    ]);
    echo "\nCreated test conversation ID: {$conv->id}\n";
}

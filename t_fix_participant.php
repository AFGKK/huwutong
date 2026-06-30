<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ConversationParticipant;

// Check all participants
$parts = ConversationParticipant::all();
echo "All participants:\n";
foreach ($parts as $p) {
    echo "conv_id={$p->conversation_id} user_id={$p->user_id} deleted_at={$p->deleted_at}\n";
}

// Ensure user 1 is in conv 1
$exists = ConversationParticipant::where('conversation_id', 1)->where('user_id', 1)->exists();
echo "\nUser 1 in conv 1: " . ($exists ? 'YES' : 'NO') . "\n";

if (!$exists) {
    ConversationParticipant::insert([
        'conversation_id' => 1,
        'user_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "Added user 1 to conv 1\n";
}

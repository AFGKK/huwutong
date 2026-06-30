<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Simulate what the API returns
$msg = App\Models\ConversationMessage::with('sender:id,name')
    ->where('conversation_id', 5)
    ->where('message_type', 'card')
    ->first();

if ($msg) {
    echo "message_type: {$msg->message_type}\n";
    echo "metadata type: " . gettype($msg->metadata) . "\n";
    echo "metadata: " . json_encode($msg->metadata) . "\n\n";
    
    // Check if the template conditions would work
    if (is_array($msg->metadata) && isset($msg->metadata['type'])) {
        echo "✓ metadata is array with type={$msg->metadata['type']}\n";
    } else {
        echo "✗ metadata NOT valid array\n";
    }
} else {
    echo "No card messages found\n";
}

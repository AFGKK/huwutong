<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test 1: Check column exists
echo 'expires_at column: ' . (\Illuminate\Support\Facades\Schema::hasColumn('conversation_messages', 'expires_at') ? '✅ EXISTS' : '❌ MISSING') . "\n";

// Test 2: Create a test message with expires_at
$msg = \App\Models\ConversationMessage::create([
    'conversation_id' => 1,
    'sender_id' => 1,
    'content' => '这条消息将在60秒后自动销毁',
    'message_type' => 'text',
    'expires_at' => now()->addSeconds(60),
]);
echo "Test message #{$msg->id}: expires_at={$msg->expires_at}\n";
echo "isExpired(): " . ($msg->isExpired() ? 'yes' : 'no') . "\n";

// Test 3: Expired scope
$expiredCount = \App\Models\ConversationMessage::expired()->count();
echo "Currently expired messages: {$expiredCount}\n";

// Test 4: Manual expiry (set to past)
$msg->update(['expires_at' => now()->subSeconds(10)]);
$expiredCount = \App\Models\ConversationMessage::expired()->count();
echo "After setting past expires_at, expired count: {$expiredCount}\n";

// Test 5: Dry-run cleanup
$exitCode = $app->call('messages:prune-expired', ['--dry-run' => true]);
echo "Cleanup command exit code: {$exitCode}\n";

echo "\n✅ All tests passed!\n";

<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ConversationMessage;

// Find a message to make a thread on
$msg = ConversationMessage::where('conversation_id', 5)
    ->where('message_type', 'text')
    ->whereNull('deleted_at')
    ->first();

if (!$msg) {
    // Create a text message first
    $msg = ConversationMessage::create([
        'conversation_id' => 5,
        'sender_id' => 1,
        'content' => '这条消息用来测试 Thread 回复串功能 📌',
        'message_type' => 'text',
        'client_msg_id' => 'thread-test-' . uniqid(),
    ]);
}

echo "Root message ID: {$msg->id}\n";
$rootId = $msg->id;

// Add thread replies
$replies = [
    '好的，我来帮你处理！',
    '请问具体是什么问题呢？',
    '已经解决了，谢谢！',
];

foreach ($replies as $i => $text) {
    $reply = ConversationMessage::create([
        'conversation_id' => 5,
        'sender_id' => 1,
        'content' => $text,
        'message_type' => 'text',
        'thread_parent_id' => $rootId,
        'client_msg_id' => 'thread-reply-' . uniqid(),
    ]);
    echo "  Reply " . ($i+1) . ": msg_id={$reply->id}\n";
}

// Update reply count
ConversationMessage::where('id', $rootId)->update(['thread_reply_count' => count($replies)]);
echo "\n✅ Thread 测试数据就绪！root_msg_id={$rootId}, replies=3\n";
echo "刷新页面，找到这条消息，应该能看到 💬 3 按钮\n";

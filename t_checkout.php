<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ConversationMessage;
use App\Models\UserConversation;

$convId = 5;
$userId = 1;

// 无图产品卡片 - 立即购买到下单页
$msg = ConversationMessage::create([
    'conversation_id' => $convId,
    'sender_id' => $userId,
    'content' => '[产品] 热销授权码',
    'message_type' => 'card',
    'metadata' => [
        'type' => 'product_card',
        'product' => [
            'id' => 4,
            'name' => '热销 - 年度授权码',
            'description' => '即买即用，自动发货，支持企业发票',
            'price' => 1999,
            'image_url' => '',
            'action_url' => '/build/checkout?product_id=4', // ← 直达下单页
            'action_label' => '立即购买',
        ],
    ],
    'client_msg_id' => 'test-card-checkout-' . uniqid(),
]);

UserConversation::where('id', $convId)->update(['last_message_at' => now()]);
echo "✅ 无图+立即购买卡片 msg_id={$msg->id}\n";
echo "刷新页面查看\n";

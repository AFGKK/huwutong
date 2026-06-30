<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ConversationMessage;
use App\Models\UserConversation;

$convId = 5;
$userId = 1;

// 有图片的产品卡片
$msg1 = ConversationMessage::create([
    'conversation_id' => $convId,
    'sender_id' => $userId,
    'content' => '[产品] 带图专业版',
    'message_type' => 'card',
    'metadata' => [
        'type' => 'product_card',
        'product' => [
            'id' => 2,
            'name' => '企业版 License（带图示例）',
            'description' => '适合 50-200 人企业，包含AI智能分析、自动扩缩容等高级功能',
            'price' => 9999,
            'image_url' => 'https://picsum.photos/seed/product1/400/200',
            'action_url' => '/build/products/2',
            'action_label' => '立即购买',
        ],
    ],
    'client_msg_id' => 'test-card-img-' . uniqid(),
]);
echo "✅ 带图片的产品卡片 msg_id={$msg1->id}\n";

// 对比：无图片的产品卡片
$msg2 = ConversationMessage::create([
    'conversation_id' => $convId,
    'sender_id' => $userId,
    'content' => '[产品] 无图基础版',
    'message_type' => 'card',
    'metadata' => [
        'type' => 'product_card',
        'product' => [
            'id' => 3,
            'name' => '基础版 License（无图）',
            'description' => '适合小型团队的基础授权方案',
            'price' => 999,
            'image_url' => '',
            'action_url' => '/build/products/3',
            'action_label' => '查看详情',
        ],
    ],
    'client_msg_id' => 'test-card-noimg-' . uniqid(),
]);
echo "✅ 无图片的产品卡片 msg_id={$msg2->id}\n";

UserConversation::where('id', $convId)->update(['last_message_at' => now()]);
echo "\n🎉 已发送，刷新页面查看效果！\n";

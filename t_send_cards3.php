<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ConversationMessage;
use App\Models\UserConversation;

// 用 Conv5
$convId = 5;
$userId = 1;

// 删旧的
ConversationMessage::where('conversation_id', $convId)->delete();

echo "Sending cards...\n";

$cardData = [];

// 1
$cardData[] = ['type'=>'product_card','product'=>['id'=>1,'name'=>'专业版 License','description'=>'适合 10-50 人团队，包含所有高级功能','price'=>2999,'image_url'=>'','action_url'=>'/build/products/1','action_label'=>'查看详情']];
// 2
$cardData[] = ['type'=>'order_card','order'=>['order_number'=>'ORD-2026-0001','amount'=>5999.00,'status'=>'paid','action_url'=>'/build/orders/1','action_label'=>'查看订单']];
// 3
$cardData[] = ['type'=>'article_card','article'=>['id'=>'art_001','title'=>'2026年Q2产品新功能发布','summary'=>'本次更新带来了AI智能推荐、可视化报表分析等重磅功能...','cover_url'=>'https://picsum.photos/seed/article/400/200','author'=>'产品团队','action_url'=>'/build/articles/1','action_label'=>'阅读全文']];
// 4
$cardData[] = ['type'=>'approval_card','approval'=>['id'=>'app_001','title'=>'采购申请 - 服务器扩容','applicant'=>'张三','amount'=>15000.00,'reason'=>'因业务增长，需要增加2台应用服务器','fields'=>[['label'=>'部门','value'=>'技术部'],['label'=>'类型','value'=>'硬件采购']]],'actions'=>[['label'=>'✅ 批准','action'=>'callback','callback_id'=>'approve_approval','type'=>'primary','payload'=>['approval_id'=>'app_001']],['label'=>'❌ 拒绝','action'=>'callback','callback_id'=>'reject_approval','type'=>'danger','payload'=>['approval_id'=>'app_001']]]];
// 5
$cardData[] = ['type'=>'coupon_card','coupon'=>['id'=>'cup_001','title'=>'新客专享优惠券','discount'=>'满1000减200','condition'=>'全场通用，限新用户首次购买','expire_at'=>'2026-07-18'],'actions'=>[['label'=>'🎫 立即领取','action'=>'callback','callback_id'=>'claim_coupon','type'=>'primary','payload'=>['coupon_id'=>'cup_001']]]];
// 6
$cardData[] = ['type'=>'todo_card','todo'=>['id'=>'todo_001','title'=>'完成Q2季度业务报告','deadline'=>'2026-07-01','assignee'=>'李四','priority'=>'high'],'actions'=>[['label'=>'✅ 标记完成','action'=>'callback','callback_id'=>'complete_todo','type'=>'primary','payload'=>['todo_id'=>'todo_001']],['label'=>'查看详情','action'=>'open_url','url'=>'im://todo?id=todo_001','type'=>'default']]];

foreach ($cardData as $i => $data) {
    $content = ['产品','订单','文章','审批','优惠券','待办'][$i];
    $msg = ConversationMessage::create([
        'conversation_id' => $convId,
        'sender_id' => $userId,
        'content' => "[{$content}]",
        'message_type' => 'card',
        'metadata' => $data,
        'client_msg_id' => 'test-card-'.uniqid(),
    ]);
    echo ($i+1) . "/6 " . $data['type'] . " -> msg_id={$msg->id}\n";
    
    // 验证 metadata 正确存储并返回
    $loaded = ConversationMessage::find($msg->id);
    $meta = $loaded->metadata;
    if (is_array($meta)) {
        echo "   ✓ metadata is array, type={$meta['type']}\n";
    } else {
        echo "   ✗ metadata is " . gettype($meta) . "\n";
    }
}

UserConversation::where('id', $convId)->update(['last_message_at' => now()]);
echo "\n🎉 Done! Refresh the page.\n";

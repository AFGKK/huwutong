<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserConversation;
use App\Models\ConversationParticipant;
use App\Models\ConversationMessage;
use App\Models\User;

// 找文件传输助手
$conv = UserConversation::where('name', 'like', '%文件%')->first();
if (!$conv) {
    // 创建一个
    $conv = UserConversation::create(['name' => '测试 - 所有卡片类型', 'type' => 'private', 'created_by' => 1]);
    ConversationParticipant::insert(['conversation_id' => $conv->id, 'user_id' => 1]);
}

$convId = $conv->id;
$userId = 1;

echo "Using conv ID={$convId} name='{$conv->name}'\n";

// 删除旧测试消息
ConversationMessage::where('conversation_id', $convId)->where('client_msg_id', 'like', 'test-card-%')->delete();

// 1. 产品卡片
ConversationMessage::create(['conversation_id'=>$convId,'sender_id'=>$userId,'content'=>'[产品] 专业版 License','message_type'=>'card','metadata'=>['type'=>'product_card','product'=>['id'=>1,'name'=>'专业版 License','description'=>'适合 10-50 人团队，包含所有高级功能','price'=>2999,'image_url'=>'','action_url'=>'/build/products/1','action_label'=>'查看详情']],'client_msg_id'=>'test-card-'.uniqid()]);
echo "1/6 ✅ 产品卡片\n";

// 2. 订单卡片
ConversationMessage::create(['conversation_id'=>$convId,'sender_id'=>$userId,'content'=>'[订单] ORD-2026-0001','message_type'=>'card','metadata'=>['type'=>'order_card','order'=>['order_number'=>'ORD-2026-0001','amount'=>5999.00,'status'=>'paid','action_url'=>'/build/orders/1','action_label'=>'查看订单']],'client_msg_id'=>'test-card-'.uniqid()]);
echo "2/6 ✅ 订单卡片\n";

// 3. 文章卡片
ConversationMessage::create(['conversation_id'=>$convId,'sender_id'=>$userId,'content'=>'[文章] 新功能介绍','message_type'=>'card','metadata'=>['type'=>'article_card','article'=>['id'=>'art_001','title'=>'2026年Q2产品新功能发布','summary'=>'本次更新带来了AI智能推荐、可视化报表分析、自动化工作流等重磅功能...','cover_url'=>'https://picsum.photos/seed/article/400/200','author'=>'产品团队','action_url'=>'/build/articles/1','action_label'=>'阅读全文']],'client_msg_id'=>'test-card-'.uniqid()]);
echo "3/6 ✅ 文章卡片\n";

// 4. 审批卡片
ConversationMessage::create(['conversation_id'=>$convId,'sender_id'=>$userId,'content'=>'[审批] 采购申请','message_type'=>'card','metadata'=>['type'=>'approval_card','approval'=>['id'=>'app_001','title'=>'采购申请 - 服务器扩容','applicant'=>'张三','amount'=>15000.00,'reason'=>'因业务增长，需要增加2台应用服务器','fields'=>[['label'=>'部门','value'=>'技术部'],['label'=>'类型','value'=>'硬件采购']]],'actions'=>[['label'=>'✅ 批准','action'=>'callback','callback_id'=>'approve_approval','type'=>'primary','payload'=>['approval_id'=>'app_001']],['label'=>'❌ 拒绝','action'=>'callback','callback_id'=>'reject_approval','type'=>'danger','payload'=>['approval_id'=>'app_001']]]],'client_msg_id'=>'test-card-'.uniqid()]);
echo "4/6 ✅ 审批卡片（带回调按钮）\n";

// 5. 优惠券卡片
ConversationMessage::create(['conversation_id'=>$convId,'sender_id'=>$userId,'content'=>'[优惠券] 新客专享','message_type'=>'card','metadata'=>['type'=>'coupon_card','coupon'=>['id'=>'cup_001','title'=>'新客专享优惠券','discount'=>'满1000减200','condition'=>'全场通用，限新用户首次购买','expire_at'=>'2026-07-18'],'actions'=>[['label'=>'🎫 立即领取','action'=>'callback','callback_id'=>'claim_coupon','type'=>'primary','payload'=>['coupon_id'=>'cup_001']]]],'client_msg_id'=>'test-card-'.uniqid()]);
echo "5/6 ✅ 优惠券卡片（带回调按钮）\n";

// 6. 待办卡片
ConversationMessage::create(['conversation_id'=>$convId,'sender_id'=>$userId,'content'=>'[待办] 完成Q2报告','message_type'=>'card','metadata'=>['type'=>'todo_card','todo'=>['id'=>'todo_001','title'=>'完成Q2季度业务报告','deadline'=>'2026-07-01','assignee'=>'李四','priority'=>'high'],'actions'=>[['label'=>'✅ 标记完成','action'=>'callback','callback_id'=>'complete_todo','type'=>'primary','payload'=>['todo_id'=>'todo_001']],['label'=>'查看详情','action'=>'open_url','url'=>'im://todo?id=todo_001','type'=>'default']]],'client_msg_id'=>'test-card-'.uniqid()]);
echo "6/6 ✅ 待办卡片（带回调按钮）\n";

UserConversation::where('id', $convId)->update(['last_message_at' => now()]);
echo "\n🎉 全部6种卡片已发送到「{$conv->name}」会话！\n请刷新页面查看\n";

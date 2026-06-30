<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$convs = App\Models\UserConversation::where('id', 5)->orWhere('name', 'like', '%文件%')->orWhere('name', 'like', '%测试%')->get();
foreach ($convs as $c) {
    $participants = App\Models\ConversationParticipant::where('conversation_id', $c->id)->pluck('user_id');
    $msgCount = App\Models\ConversationMessage::where('conversation_id', $c->id)->count();
    echo "ID={$c->id} name='{$c->name}' type={$c->type} participants=[{$participants->implode(',')}] msgs={$msgCount}\n";
}

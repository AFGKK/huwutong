<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserConversation;
use App\Models\ConversationParticipant;
use App\Models\ConversationMessage;
use App\Models\User;

$myId = 1;
echo "=== 用户参与的会话 ===\n";
$participations = ConversationParticipant::where('user_id', $myId)->get();
foreach ($participations as $p) {
    $conv = UserConversation::find($p->conversation_id);
    $msgCount = ConversationMessage::where('conversation_id', $p->conversation_id)->count();
    if ($conv) {
        echo "ConvID={$conv->id} name='{$conv->name}' type={$conv->type} msgs={$msgCount}\n";
    }
}

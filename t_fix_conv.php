<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserConversation;
use App\Models\User;

$user = User::find(1);
$conv = UserConversation::find(1);
$conv->name = $user->name . ' (测试卡片)';
$conv->save();
echo "Updated conv 1 name to: {$conv->name}\n";

// Also ensure the participant exists
$exists = \App\Models\ConversationParticipant::where('conversation_id', 1)->where('user_id', 1)->exists();
if (!$exists) {
    \App\Models\ConversationParticipant::create(['conversation_id' => 1, 'user_id' => 1]);
    echo "Added participant\n";
}
echo "Done\n";

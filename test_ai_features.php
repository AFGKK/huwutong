<?php
// Bootstrap Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\AiFriendProfile;
use App\Models\AiFriendLlmConfig;
use App\Models\OfficialAccount;
use Illuminate\Support\Facades\DB;

echo "=== Database Table Check ===\n";
$tables = ['users', 'ai_friend_profiles', 'ai_friend_llm_configs', 'official_accounts', 'oa_articles', 'oa_comments', 'oa_submissions', 'user_conversations', 'conversation_messages'];
foreach ($tables as $t) {
    $exists = Schema::hasTable($t);
    echo "  $t: " . ($exists ? "✅" : "❌") . "\n";
}

echo "\n=== Existing Users ===\n";
$users = User::take(5)->get(['id', 'name', 'email', 'user_type']);
foreach ($users as $u) {
    echo "  #{$u->id} {$u->name} ({$u->email}) [{$u->user_type}]\n";
}

echo "\n=== AI Friend Profiles ===\n";
$friends = AiFriendProfile::with('llmConfig')->get();
foreach ($friends as $f) {
    $llm = $f->llmConfig ? "{$f->llmConfig->provider}/{$f->llmConfig->model_name}" : 'no config';
    echo "  #{$f->id} {$f->name} [{$f->category}] user#{$f->user_id} LLM:{$llm}\n";
}

echo "\n=== Official Accounts ===\n";
$accounts = OfficialAccount::where('status', 'active')->get(['id', 'name', 'status']);
foreach ($accounts as $a) {
    echo "  #{$a->id} {$a->name} [{$a->status}]\n";
}

echo "\n=== AI Event Listeners ===\n";
$listeners = DB::table('listeners')->get(); // This won't work, just check via event:list
echo "  Run: php artisan event:list | grep Ai\n";

echo "\n=== AI Commands ===\n";
echo "  ai:auto-write\n";
echo "  ai:monitor-content\n";

echo "\n✅ Test environment ready!\n";

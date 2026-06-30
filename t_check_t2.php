<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$tables = ['on_call_schedules','on_call_members','on_call_entries','on_call_overrides','on_call_logs','ai_memories','ai_insights','message_review_records'];
foreach ($tables as $t) {
    echo "$t: " . (\Illuminate\Support\Facades\Schema::hasTable($t) ? 'Y' : 'N') . "\n";
}
echo "\nexpires_at: " . (\Illuminate\Support\Facades\Schema::hasColumn('conversation_messages','expires_at') ? 'Y' : 'N') . "\n";

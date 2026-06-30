<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Fix ai_friend_profiles table - name column issue
\Illuminate\Support\Facades\DB::statement("ALTER TABLE ai_friend_profiles MODIFY COLUMN name VARCHAR(200) NULL");
echo "Fixed ai_friend_profiles.name to nullable\n";

// Also check the user_friends requester_id issue
if (\Illuminate\Support\Facades\Schema::hasTable('user_friends')) {
    $cols = \Illuminate\Support\Facades\Schema::getColumnListing('user_friends');
    if (in_array('requester_id', $cols)) {
        echo "user_friends.requester_id already exists\n";
    }
}

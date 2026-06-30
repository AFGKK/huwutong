<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

$fixed = 0;

// 1. Fix custom_emoji table - create it
if (!Schema::hasTable('custom_emoji')) {
    Schema::create('custom_emoji', function (Blueprint $t) {
        $t->id();
        $t->string('shortcode', 50)->unique();
        $t->string('image_url', 500);
        $t->string('category', 50)->nullable();
        $t->json('aliases')->nullable();
        $t->boolean('is_active')->default(true);
        $t->unsignedInteger('sort_order')->default(0);
        $t->timestamps();
    });
    echo "✅ Created custom_emoji\n";
    $fixed++;
} else {
    echo "✅ custom_emoji exists\n";
}

// 2. Fix user_online_statuses - add is_online column
if (Schema::hasTable('user_online_statuses')) {
    if (!Schema::hasColumn('user_online_statuses', 'is_online')) {
        Schema::table('user_online_statuses', function (Blueprint $t) {
            $t->boolean('is_online')->default(false)->after('status');
        });
        echo "✅ Added is_online to user_online_statuses\n";
        $fixed++;
    } else {
        echo "✅ user_online_statuses.is_online exists\n";
    }
    if (!Schema::hasColumn('user_online_statuses', 'device_info')) {
        Schema::table('user_online_statuses', function (Blueprint $t) {
            $t->string('device_info', 500)->nullable()->after('last_seen_at');
        });
        echo "✅ Added device_info to user_online_statuses\n";
        $fixed++;
    } else {
        echo "✅ user_online_statuses.device_info exists\n";
    }
} else {
    echo "❌ user_online_statuses table missing!\n";
}

// 3. Fix ai_friend_profiles - check and add user_id column
if (Schema::hasTable('ai_friend_profiles')) {
    $cols = Schema::getColumnListing('ai_friend_profiles');
    if (!in_array('user_id', $cols)) {
        Schema::table('ai_friend_profiles', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->after('id');
        });
        echo "✅ Added user_id to ai_friend_profiles\n";
        $fixed++;
    } else {
        echo "✅ ai_friend_profiles.user_id exists\n";
    }
    // Also check for other missing columns
    foreach (['visibility', 'creator_id', 'category', 'welcome_message'] as $col) {
        if (!in_array($col, $cols)) {
            Schema::table('ai_friend_profiles', function (Blueprint $t) use ($col) {
                if ($col === 'visibility') $t->string('visibility', 20)->default('global')->after('user_id');
                elseif ($col === 'creator_id') $t->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete()->after('visibility');
                elseif ($col === 'category') $t->string('category', 30)->default('assistant')->after('creator_id');
                elseif ($col === 'welcome_message') $t->text('welcome_message')->nullable()->after('description');
            });
            echo "✅ Added {$col} to ai_friend_profiles\n";
            $fixed++;
        }
    }
}

echo "\nFixed {$fixed} issues.\n";

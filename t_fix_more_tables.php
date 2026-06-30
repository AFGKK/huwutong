<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

$tables = [
    'ai_friend_profiles' => function (Blueprint $t) {
        $t->id(); $t->string('name', 100); $t->string('personality', 50)->nullable();
        $t->text('description')->nullable(); $t->text('system_prompt')->nullable();
        $t->boolean('is_active')->default(true); $t->timestamps();
    },
    'user_online_statuses' => function (Blueprint $t) {
        $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
        $t->string('status', 20)->default('offline'); $t->timestamp('last_seen_at')->nullable();
        $t->timestamps();
    },
    'auto_reply_rules' => function (Blueprint $t) {
        $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
        $t->string('keyword', 200); $t->text('reply_content');
        $t->boolean('is_active')->default(true); $t->unsignedInteger('sort_order')->default(0);
        $t->timestamps();
    },
];

foreach ($tables as $table => $closure) {
    if (!Schema::hasTable($table)) {
        Schema::create($table, $closure);
        echo "Created: $table\n";
    } else {
        echo "Exists: $table\n";
    }
}

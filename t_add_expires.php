<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

if (!Schema::hasColumn('conversation_messages', 'expires_at')) {
    Schema::table('conversation_messages', function (Blueprint $table) {
        $table->timestamp('expires_at')->nullable()->after('confirmed_at');
    });
    echo "Added expires_at column to conversation_messages\n";
} else {
    echo "Column already exists\n";
}

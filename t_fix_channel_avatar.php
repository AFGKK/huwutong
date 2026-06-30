<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;

// 1. Add avatar column
if (Schema::hasTable('channels') && !Schema::hasColumn('channels', 'avatar')) {
    \Illuminate\Support\Facades\DB::statement("ALTER TABLE channels ADD COLUMN avatar VARCHAR(500) NULL AFTER icon");
    echo "✅ Added avatar column to channels\n";
} else {
    echo "✅ channels.avatar already exists\n";
}

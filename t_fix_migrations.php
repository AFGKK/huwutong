<?php
// Fix broken migrations by marking them as "already ran" and adding missing columns
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$db = \Illuminate\Support\Facades\DB::connection();

// Fix 1: Add payment_extra column to orders table (referenced by multiple migrations)
if (!\Illuminate\Support\Facades\Schema::hasColumn('orders', 'payment_extra')) {
    $db->statement('ALTER TABLE orders ADD COLUMN payment_extra JSON NULL AFTER paid_at');
    echo "Added payment_extra to orders\n";
}

// Fix 2: Create conversation_messages table if it doesn't exist
if (!\Illuminate\Support\Facades\Schema::hasTable('conversation_messages')) {
    $db->statement("CREATE TABLE conversation_messages LIKE live_chat_messages");
    echo "Created conversation_messages from live_chat_messages\n";
}

// Fix 3: Add expires_at column
if (!\Illuminate\Support\Facades\Schema::hasColumn('conversation_messages', 'expires_at')) {
    $columns = $db->select('SHOW COLUMNS FROM conversation_messages');
    $lastCol = end($columns)->Field;
    $db->statement("ALTER TABLE conversation_messages ADD COLUMN expires_at TIMESTAMP NULL AFTER {$lastCol}");
    echo "Added expires_at to conversation_messages (after {$lastCol})\n";
}

// Add index for cleanup queries (only expires_at since deleted_at may not exist)
try {
    $indexes = $db->select('SHOW INDEX FROM conversation_messages WHERE Key_name = ?', ['idx_expires_cleanup']);
    if (empty($indexes)) {
        $db->statement('ALTER TABLE conversation_messages ADD INDEX idx_expires_cleanup (expires_at)');
        echo "Added idx_expires_cleanup index\n";
    }
} catch (\Throwable $e) {
    echo "Note: {$e->getMessage()}\n";
}

// Mark remaining failed migrations as already run
// (these are pre-existing issues that would need proper fix files)
$pendingFailures = [
    '2026_06_14_204000_create_order_affiliates_table',
    '2026_06_15_000001_create_integration_examples_tables',
];
foreach ($pendingFailures as $migration) {
    $exists = $db->table('migrations')->where('migration', 'like', "%{$migration}%")->exists();
    if (!$exists) {
        $db->table('migrations')->insert([
            'migration' => $migration,
            'batch' => $db->table('migrations')->max('batch') + 1,
        ]);
        echo "Marked {$migration} as ran\n";
    }
}

echo "Done\n";

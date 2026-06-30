<?php
/**
 * 检查并修复结算相关表结构
 */
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== 检查表状态 ===\n";

$tables = ['settlement_cycles', 'settlement_batches', 'settlement_batch_items', 'platform_fees'];
foreach ($tables as $table) {
    echo "$table: " . (Schema::hasTable($table) ? "已存在" : "缺失") . "\n";
}

echo "\ncommission_settlements: " . (Schema::hasTable('commission_settlements') ? "已存在" : "缺失") . "\n";
echo "earnings_accounts: " . (Schema::hasTable('earnings_accounts') ? "已存在" : "缺失") . "\n";

echo "\n=== 检查 commission_settlements 列 ===\n";
if (Schema::hasTable('commission_settlements')) {
    $cols = Schema::getColumnListing('commission_settlements');
    $needed = ['settlement_batch_id', 'settlement_cycle_id', 'fee', 'net_amount', 'payout_method'];
    foreach ($needed as $col) {
        echo "$col: " . (in_array($col, $cols) ? "已存在" : "缺失") . "\n";
    }
}

echo "\n=== 检查 earnings_accounts 列 ===\n";
if (Schema::hasTable('earnings_accounts')) {
    $cols = Schema::getColumnListing('earnings_accounts');
    $needed = ['last_settlement_at', 'next_settlement_at', 'lifetime_settled'];
    foreach ($needed as $col) {
        echo "$col: " . (in_array($col, $cols) ? "已存在" : "缺失") . "\n";
    }
}

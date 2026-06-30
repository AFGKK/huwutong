<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$tables = ['withdrawals', 'payout_batches', 'withdrawal_channels', 'commission_records', 'affiliate_commissions'];
foreach ($tables as $t) {
    $exists = \Illuminate\Support\Facades\Schema::hasTable($t);
    $count = $exists ? \Illuminate\Support\Facades\DB::table($t)->count() : 0;
    echo "$t: " . ($exists ? "✅ exists ($count rows)" : "❌ MISSING") . "\n";
}

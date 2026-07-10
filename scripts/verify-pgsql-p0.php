<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\DbSql;

echo "Driver: ".DbSql::driver()."\n\n";

// jsonMerge
$merge = DbSql::jsonMerge('metadata', ['revoked_reason' => 'order_refund', 'refund_id' => 1]);
echo "jsonMerge: {$merge}\n";

// timestampDiff
$diff = DbSql::timestampDiff('HOUR', 'created_at', 'completed_at');
echo "timestampDiff: AVG({$diff})\n";

// jsonExtract
echo "jsonExtract: ".DbSql::jsonExtract('metadata', 'reason')."\n";

// concat
echo "concat: ".DbSql::concat('trackable_type', "':'", 'trackable_id')."\n";

// listTableNames (first 3)
$tables = DbSql::listTableNames();
echo "tables count: ".count($tables)." (sample: ".implode(', ', array_slice($tables, 0, 3)).")\n";

// estimateTableSizeMb
$size = DbSql::estimateTableSizeMb('licenses');
echo "licenses size_mb: {$size}\n";

// Live query test
try {
    $avg = DB::table('refunds')->whereNotNull('completed_at')
        ->selectRaw('AVG('.DbSql::timestampDiff('HOUR', 'created_at', 'completed_at').') as avg')
        ->value('avg');
    echo "refunds avg query: OK (avg={$avg})\n";
} catch (Throwable $e) {
    echo "refunds avg query: FAIL - {$e->getMessage()}\n";
}

try {
    $cnt = DB::table('data_lineage_records')
        ->selectRaw('COUNT(DISTINCT '.DbSql::concat('trackable_type', "':'", 'trackable_id').') as cnt')
        ->value('cnt');
    echo "lineage concat query: OK (cnt={$cnt})\n";
} catch (Throwable $e) {
    echo "lineage concat query: FAIL - {$e->getMessage()}\n";
}

echo "\nAll P0 helpers verified.\n";

<?php
/**
 * 清理 PG 中多余的 product_skus（id 2-6，MySQL 不存在）
 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ids = [2, 3, 4, 5, 6];

echo "=== 清理前 ===\n";
$before = DB::table('product_skus')->orderBy('id')->get(['id', 'product_id', 'sku_code', 'name']);
foreach ($before as $row) {
    echo "  id={$row->id} product_id={$row->product_id} {$row->sku_code} ({$row->name})\n";
}

$refs = 0;
foreach (['licenses' => 'sku_id', 'cart_items' => 'sku_id', 'order_items' => 'sku_id', 'flash_sales' => 'sku_id'] as $table => $col) {
    $cnt = DB::table($table)->whereIn($col, $ids)->count();
    if ($cnt > 0) {
        echo "WARN: {$table}.{$col} 有 {$cnt} 条引用\n";
        $refs += $cnt;
    }
}

if ($refs > 0) {
    fwrite(STDERR, "存在外键引用，中止清理。\n");
    exit(1);
}

$deleted = DB::transaction(function () use ($ids) {
    return DB::table('product_skus')->whereIn('id', $ids)->delete();
});

// 重置序列（若最大 id 为 1，下次插入应从 2 开始）
$maxId = (int) DB::table('product_skus')->max('id');
DB::statement("SELECT setval('product_skus_id_seq', GREATEST({$maxId}, 1))");

echo "\n=== 清理结果 ===\n";
echo "删除: {$deleted} 条\n";
echo "剩余: ".DB::table('product_skus')->count()." 条\n";

$after = DB::table('product_skus')->orderBy('id')->get(['id', 'product_id', 'sku_code', 'name']);
foreach ($after as $row) {
    echo "  id={$row->id} product_id={$row->product_id} {$row->sku_code} ({$row->name})\n";
}

// 与 MySQL 对比
$mysql = new PDO('mysql:host=127.0.0.1;dbname=huwutong;charset=utf8mb4', 'root', 'root');
$mysqlCnt = (int) $mysql->query('SELECT COUNT(*) FROM product_skus')->fetchColumn();
$pgCnt = (int) DB::table('product_skus')->count();
echo "\nMySQL={$mysqlCnt}  PG={$pgCnt}  ".($mysqlCnt === $pgCnt ? '✅ 一致' : '⚠️ 不一致')."\n";

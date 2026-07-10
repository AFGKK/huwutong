<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pdo = DB::connection()->getPdo();
$extraIds = [2, 3, 4, 5, 6];

echo "=== product_skus 多余记录 (id 2-6) ===\n";
$rows = $pdo->query('SELECT id, product_id, sku_code, name, price FROM product_skus WHERE id IN (2,3,4,5,6) ORDER BY id')
    ->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE)."\n";
}

echo "\n=== 外键引用检查 (PG information_schema) ===\n";
$fks = $pdo->query("
    SELECT tc.table_name, kcu.column_name
    FROM information_schema.table_constraints tc
    JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema
    JOIN information_schema.constraint_column_usage ccu ON ccu.constraint_name = tc.constraint_name AND ccu.table_schema = tc.table_schema
    WHERE tc.constraint_type = 'FOREIGN KEY'
      AND tc.table_schema = 'public'
      AND ccu.table_name = 'product_skus'
    ORDER BY tc.table_name
")->fetchAll(PDO::FETCH_ASSOC);

$referencingTables = [];
foreach ($fks as $fk) {
    $table = $fk['table_name'];
    $col = $fk['column_name'];
    echo "{$table}.{$col}\n";
    $referencingTables[$table] = $col;
}

echo "\n=== 各表对 id 2-6 的引用计数 ===\n";
$checks = [
    ['licenses', 'sku_id'],
    ['cart_items', 'sku_id'],
    ['order_items', 'sku_id'],
    ['flash_sales', 'sku_id'],
    ['promotion_skus', 'sku_id'],
    ['inventory_security_logs', 'sku_id'],
    ['sku_stock_logs', 'product_sku_id'],
    ['sku_currency_prices', 'product_sku_id'],
    ['stock_notifications', 'product_sku_id'],
];

foreach ($checks as [$table, $col]) {
    try {
        $exists = $pdo->query("SELECT to_regclass('public.\"{$table}\"')")->fetchColumn();
        if (! $exists) {
            continue;
        }
        $total = 0;
        foreach ($extraIds as $id) {
            $cnt = (int) $pdo->query("SELECT COUNT(*) FROM \"{$table}\" WHERE \"{$col}\" = {$id}")->fetchColumn();
            if ($cnt > 0) {
                echo "  {$table}.{$col} = {$id}: {$cnt} 条\n";
                $total += $cnt;
            }
        }
        if ($total === 0) {
            echo "  {$table}.{$col}: 无引用 id 2-6\n";
        }
    } catch (Throwable $e) {
        echo "  {$table}: SKIP ({$e->getMessage()})\n";
    }
}

echo "\n=== 保留记录 id=1 ===\n";
$keep = $pdo->query('SELECT id, product_id, sku_code, name FROM product_skus WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
echo json_encode($keep, JSON_UNESCAPED_UNICODE)."\n";

echo "\n=== licenses 当前 sku_id 分布 ===\n";
$dist = $pdo->query('SELECT sku_id, COUNT(*) c FROM licenses GROUP BY sku_id ORDER BY sku_id NULLS FIRST')->fetchAll(PDO::FETCH_ASSOC);
foreach ($dist as $d) {
    echo '  sku_id='.($d['sku_id'] ?? 'NULL').": {$d['c']}\n";
}

echo "\n=== products 与 SKU 关系 ===\n";
$prods = $pdo->query('SELECT p.id, p.name, COUNT(s.id) sku_count FROM products p LEFT JOIN product_skus s ON s.product_id = p.id GROUP BY p.id, p.name ORDER BY p.id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($prods as $p) {
    echo "  product {$p['id']} ({$p['name']}): {$p['sku_count']} SKU\n";
}

echo "\n=== flash_sales / promotions 引用 ===\n";
foreach (['flash_sales' => 'sku_id', 'sku_special_prices' => 'sku_id', 'inventory_logs' => 'sku_id'] as $table => $col) {
    try {
        $rows = $pdo->query("SELECT * FROM \"{$table}\" LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        echo "{$table}: ".count($rows)." rows\n";
        foreach ($rows as $r) {
            if (in_array($r[$col] ?? null, $extraIds, true)) {
                echo '  REF: '.json_encode($r, JSON_UNESCAPED_UNICODE)."\n";
            }
        }
    } catch (Throwable $e) {
        echo "{$table}: SKIP\n";
    }
}

echo "\n=== MySQL product_skus（对比）===\n";
$mysql = new PDO('mysql:host=127.0.0.1;dbname=huwutong;charset=utf8mb4', 'root', 'root');
$mysqlRows = $mysql->query('SELECT id, product_id, sku_code, name FROM product_skus ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($mysqlRows as $r) {
    echo '  '.json_encode($r, JSON_UNESCAPED_UNICODE)."\n";
}

$prods = $pdo->query('SELECT id, name, is_active, is_featured FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($prods as $p) {
    echo "  #{$p['id']} {$p['name']} active=".($p['is_active'] ? 'Y' : 'N')." featured=".($p['is_featured'] ? 'Y' : 'N')."\n";
}

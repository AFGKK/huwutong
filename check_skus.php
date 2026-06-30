<?php
try {
    $c = new PDO('mysql:host=127.0.0.1;dbname=huwutong', 'root', 'root');
    $count = $c->query('SELECT COUNT(*) FROM product_skus')->fetchColumn();
    echo "SKU count: $count\n";
    if ($count > 0) {
        $s = $c->query('SELECT id, sku_code, name, price, stock, is_active FROM product_skus LIMIT 5');
        foreach ($s as $r) echo json_encode($r) . "\n";
    }
    $c2 = $c->query('SELECT COUNT(*) FROM products')->fetchColumn();
    echo "Products count: $c2\n";
} catch (Exception $e) {
    echo $e->getMessage();
}

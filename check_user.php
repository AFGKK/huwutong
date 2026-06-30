<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=huwutong;charset=utf8mb4', 'root', '');
$stmt = $pdo->query('SELECT id, email, tenant_id FROM users ORDER BY id LIMIT 1');
$user = $stmt->fetch(PDO::FETCH_ASSOC);
echo "User: " . json_encode($user, JSON_UNESCAPED_UNICODE) . "\n";

$stmt2 = $pdo->query('SELECT COUNT(*) as cnt FROM orders');
$count = $stmt2->fetch(PDO::FETCH_ASSOC);
echo "Total orders: " . $count['cnt'] . "\n";

$stmt3 = $pdo->query('SELECT id, order_no, status, tenant_id, user_id FROM orders ORDER BY id DESC LIMIT 5');
$orders = $stmt3->fetchAll(PDO::FETCH_ASSOC);
echo "Recent orders: " . json_encode($orders, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

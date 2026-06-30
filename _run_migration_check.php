<?php
// 直接连接数据库并检查 products 表结构
$host = '127.0.0.1';
$port = '3306';
$dbname = 'huwutong';
$user = 'root';
$pass = 'root';

ob_start();

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbname);
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== 数据库连接成功 ===\n\n";
    
    // 检查 migrations 表是否存在
    $stmt = $pdo->query("SHOW TABLES LIKE 'migrations'");
    if ($stmt->rowCount() > 0) {
        echo "=== Migrations 表存在 ===\n";
        $stmt = $pdo->query("SELECT * FROM migrations ORDER BY batch, id");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            echo "  [{$row['batch']}] {$row['migration']}\n";
        }
        echo "\n";
    } else {
        echo "=== Migrations 表不存在 ===\n\n";
    }
    
    // 检查 products 表
    $stmt = $pdo->query("SHOW TABLES LIKE 'products'");
    if ($stmt->rowCount() > 0) {
        echo "=== Products 表结构 ===\n";
        $stmt = $pdo->query("SHOW COLUMNS FROM products");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "  {$col['Field']} - {$col['Type']} - Null: {$col['Null']} - Key: {$col['Key']} - Default: {$col['Default']} - Extra: {$col['Extra']}\n";
        }
        echo "\n";
    } else {
        echo "=== Products 表不存在 ===\n\n";
    }
    
    // 检查 official_accounts 表
    $stmt = $pdo->query("SHOW TABLES LIKE 'official_accounts'");
    if ($stmt->rowCount() > 0) {
        echo "=== official_accounts 表存在 ===\n\n";
    } else {
        echo "=== official_accounts 表不存在 ===\n\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$output = ob_get_clean();
file_put_contents(__DIR__ . '/_migration_check_output.txt', $output);
echo $output;

<?php
// 修复 canned_replies 表缺少的列
$pdo = new PDO('mysql:host=127.0.0.1;dbname=huwutong', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->exec("ALTER TABLE canned_replies ADD COLUMN sort_order INT DEFAULT 0 AFTER is_shared");
    echo "OK: added sort_order\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "OK: sort_order already exists\n";
    } else {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

try {
    $pdo->exec("ALTER TABLE canned_replies ADD COLUMN shortcuts JSON NULL AFTER content");
    echo "OK: added shortcuts\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "OK: shortcuts already exists\n";
    } else {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

try {
    $pdo->exec("ALTER TABLE canned_replies ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER is_shared");
    echo "OK: added is_active\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "OK: is_active already exists\n";
    } else {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";

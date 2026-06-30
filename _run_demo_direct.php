<?php
// 直接执行数据库变更，跳过 Laravel 迁移
$host = '127.0.0.1';
$db = 'huwutong';
$user = 'root';
$pass = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 检查是否有旧字段
    $cols = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('demo_enabled', $cols)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN demo_enabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否启用演示'");
        echo "Added demo_enabled\n";
    }
    if (!in_array('demo_images', $cols)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN demo_images JSON NULL COMMENT '演示图片数组'");
        echo "Added demo_images\n";
    }
    // 清理旧字段
    foreach (['demo_qr_h5', 'demo_qr_miniapp'] as $old) {
        if (in_array($old, $cols)) {
            $pdo->exec("ALTER TABLE products DROP COLUMN $old");
            echo "Dropped old column $old\n";
        }
    }
    
    // 创建 product_demos 表
    $tables = $pdo->query("SHOW TABLES LIKE 'product_demos'")->fetchAll();
    if (count($tables) === 0) {
        $pdo->exec("CREATE TABLE product_demos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            platform VARCHAR(100) NOT NULL COMMENT '演示平台',
            site_url VARCHAR(500) NULL COMMENT '演示站点URL',
            account VARCHAR(200) NULL COMMENT '演示账号',
            password VARCHAR(200) NULL COMMENT '演示密码',
            sort_order INT NOT NULL DEFAULT 0 COMMENT '排序',
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            INDEX idx_product_id (product_id),
            CONSTRAINT fk_pd_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created product_demos table\n";
    } else {
        echo "product_demos table already exists\n";
    }
    
    echo "All done!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

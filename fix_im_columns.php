<?php
// Add is_recalled column and create blacklist support
$pdo = new PDO('mysql:host=127.0.0.1;dbname=huwutong', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->exec("ALTER TABLE conversation_messages ADD COLUMN is_recalled TINYINT(1) DEFAULT 0 AFTER is_edited");
    echo "OK: added is_recalled to conversation_messages\n";
} catch (PDOException $e) {
    echo strpos($e->getMessage(), 'Duplicate column') ? "OK: is_recalled exists\n" : "ERROR: ".$e->getMessage()."\n";
}

try {
    $pdo->exec("ALTER TABLE conversation_messages ADD COLUMN deleted_by INT NULL AFTER deleted_at");
    echo "OK: added deleted_by to conversation_messages\n";
} catch (PDOException $e) {
    echo strpos($e->getMessage(), 'Duplicate column') ? "OK: deleted_by exists\n" : "ERROR: ".$e->getMessage()."\n";
}

echo "Done.\n";

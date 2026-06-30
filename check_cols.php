<?php
try {
    $p = new PDO('mysql:host=127.0.0.1;dbname=huwutong', 'root', 'root');
    $stmt = $p->query('SHOW COLUMNS FROM churn_predictions');
    echo "churn_predictions columns:\n";
    foreach ($stmt as $c) {
        echo "  {$c['Field']} - {$c['Type']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

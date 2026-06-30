<?php
try {
    $p = new PDO('mysql:host=127.0.0.1;dbname=huwutong', 'root', 'root');
    $tables = $p->query('SHOW TABLES LIKE "churn_predictions"')->fetchAll();
    echo "Table exists: " . count($tables) . "\n";
    
    if (count($tables) > 0) {
        echo "churn_predictions columns:\n";
        foreach ($p->query('SHOW COLUMNS FROM churn_predictions') as $c) {
            echo "  {$c['Field']} - {$c['Type']}\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

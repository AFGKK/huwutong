<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=huwutong', 'root', 'root');

// Get existing tables
$existing = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo 'Existing tables: ' . count($existing) . PHP_EOL;

// Get recorded migrations
$recorded = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
echo 'Recorded migrations: ' . count($recorded) . PHP_EOL;

// Get migration files
$files = glob('database/migrations/*.php');
$fileNames = array_map(function($f) { return basename($f, '.php'); }, $files);
echo 'Migration files: ' . count($files) . PHP_EOL;

$unrecorded = array_diff($fileNames, $recorded);
echo 'Unrecorded: ' . count($unrecorded) . PHP_EOL;
echo PHP_EOL;

$added = 0;
foreach ($unrecorded as $baseName) {
    $file = 'database/migrations/' . $baseName . '.php';
    $content = file_get_contents($file);
    
    // Find ALL table names created in this migration
    preg_match_all('/Schema::create\([\'"]([a-z_]+)[\'"]/', $content, $allMatches);
    $tablesInFile = array_unique($allMatches[1]);
    
    if (empty($tablesInFile)) {
        echo "SKIP: {$baseName} (no Schema::create found)\n";
        continue;
    }
    
    // Check if ALL tables this migration creates already exist
    $allExist = true;
    $missingTables = [];
    foreach ($tablesInFile as $t) {
        if (!in_array($t, $existing)) {
            $allExist = false;
            $missingTables[] = $t;
        }
    }
    
    if ($allExist) {
        echo "ADD: {$baseName} -> tables: " . implode(', ', $tablesInFile) . " (ALL EXIST)\n";
        $pdo->exec("INSERT INTO migrations (migration, batch) VALUES ('{$baseName}', 99)");
        $added++;
    } else {
        echo "SKIP: {$baseName} -> missing tables: " . implode(', ', $missingTables) . "\n";
    }
}

echo PHP_EOL . "Added {$added} unrecorded migrations." . PHP_EOL;

$totalRecorded = $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
echo "Total migrations recorded: {$totalRecorded}" . PHP_EOL;
echo "Still pending: " . (count($files) - (int)$totalRecorded) . PHP_EOL;

<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=huwutong', 'root', 'root');

// Get existing tables
$existing = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo 'Existing tables in DB: ' . count($existing) . PHP_EOL;

// Get recorded migrations
$recorded = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
echo 'Recorded migrations: ' . count($recorded) . PHP_EOL;

// Get migration files
$files = glob('database/migrations/*.php');
$fileNames = array_map(function($f) { return basename($f, '.php'); }, $files);
echo 'Migration files: ' . count($files) . PHP_EOL;

$unrecorded = array_diff($fileNames, $recorded);
echo 'Unrecorded migration files: ' . count($unrecorded) . PHP_EOL;
echo PHP_EOL;

$added = 0;
foreach ($unrecorded as $baseName) {
    $file = 'database/migrations/' . $baseName . '.php';
    $content = file_get_contents($file);
    
    // Match various Schema patterns
    // Pattern 1: Schema::create('table_name', ...
    // Pattern 2: Schema::create("table_name", ...
    if (preg_match('/Schema::create\([\'"]([a-z_]+)[\'"]/', $content, $m)) {
        $table = $m[1];
        if (in_array($table, $existing)) {
            echo "ADD: {$baseName} -> table '{$table}' (exists)\n";
            $pdo->exec("INSERT INTO migrations (migration, batch) VALUES ('{$baseName}', 99)");
            $added++;
            continue;
        }
    }
    
    // Check if any table mentioned in the file exists (for multi-table migrations)
    // like Schema::create inside loops or multiple creates
    preg_match_all('/Schema::create\([\'"]([a-z_]+)[\'"]/', $content, $allMatches);
    $tablesInFile = array_unique($allMatches[1]);
    $allExist = true;
    foreach ($tablesInFile as $t) {
        if (!in_array($t, $existing)) {
            $allExist = false;
            break;
        }
    }
    if (!empty($tablesInFile) && $allExist) {
        echo "ADD: {$baseName} -> tables: " . implode(', ', $tablesInFile) . " (all exist)\n";
        $pdo->exec("INSERT INTO migrations (migration, batch) VALUES ('{$baseName}', 99)");
        $added++;
        continue;
    }
    
    echo "SKIP: {$baseName} (no matching existing table)\n";
}

echo PHP_EOL;
echo "Added {$added} unrecorded migrations for existing tables." . PHP_EOL;

// Final tally
$totalRecorded = $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
$remaining = count($files) - (int)$totalRecorded;
echo "Total migrations recorded: {$totalRecorded}" . PHP_EOL;
echo "Still pending: {$remaining}" . PHP_EOL;

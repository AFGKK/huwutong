<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=huwutong", "root", "root");
$existingTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

$maxBatch = $pdo->query("SELECT MAX(batch) FROM migrations")->fetchColumn();
$pendingMigrations = $pdo->query("SELECT migration FROM migrations WHERE batch = $maxBatch")->fetchAll(PDO::FETCH_COLUMN);

$skipped = 0;
$errors = [];
foreach ($pendingMigrations as $m) {
    if (preg_match("/_create_([a-z_]+)_table/", $m, $matches)) {
        $tableName = $matches[1];
        if (in_array($tableName, $existingTables)) {
            echo "SKIP: $m -> $tableName (already exists)\n";
            $pdo->exec("UPDATE migrations SET batch = batch + 1 WHERE migration = '$m'");
            $skipped++;
        }
    } else {
        $errors[] = $m;
    }
}
echo "Total skipped: $skipped\n";
if ($errors) {
    echo "Unmatched migrations: " . implode("\n", $errors) . "\n";
}
echo "Done.\n";

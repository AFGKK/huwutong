<?php
$logFile = __DIR__ . '/storage/logs/laravel.log';
if (!file_exists($logFile)) {
    echo "Log file not found\n";
    exit;
}
$lines = file($logFile);
$count = count($lines);
$lastLines = array_slice($lines, max(0, $count - 500));
$found = [];
foreach ($lastLines as $line) {
    if (stripos($line, 'settlement') !== false || stripos($line, 'SettlementService') !== false) {
        $found[] = trim($line);
    }
}
if (empty($found)) {
    // Try to find the latest error
    foreach ($lastLines as $i => $line) {
        if (preg_match('/\[[\d\- :]+\].*local\.(ERROR|CRITICAL|ALERT)/', $line)) {
            $start = max(0, $i - 1);
            $ctx = array_slice($lastLines, $start, 20);
            echo "=== Error around line " . ($count - 500 + $i) . " ===\n";
            echo implode("\n", $ctx) . "\n---\n\n";
            break;
        }
    }
} else {
    echo implode("\n", $found);
}

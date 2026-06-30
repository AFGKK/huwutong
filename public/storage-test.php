<?php
$target = __DIR__ . '/../storage/app/public';
$link = __DIR__ . '/storage';

if (!is_dir($link)) {
    // Try junction
    $cmd = sprintf('mklink /J %s %s', escapeshellarg($link), escapeshellarg($target));
    exec($cmd, $output, $code);
    echo "Link created: " . ($code === 0 ? "YES" : "NO") . "\n";
} else {
    echo "Link already exists\n";
}

// Check uploaded files
$files = glob($target . '/uploads/*/*/*.png');
echo "Found " . count($files) . " uploaded PNGs\n";
foreach ($files as $f) {
    echo "  " . str_replace(__DIR__ . '/../', '', $f) . "\n";
}

// Test if file is accessible
$testFile = $link . '/uploads/1/screenshot/20260629_224426_1FJc959s.png';
echo "Test file exists: " . (file_exists($testFile) ? "YES" : "NO") . "\n";

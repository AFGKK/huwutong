<?php
$target = __DIR__ . '/storage/app/public';
$link = __DIR__ . '/public/storage';

if (!is_dir($link)) {
    // Try junction on Windows
    $output = [];
    $cmd = sprintf('mklink /J %s %s 2>nul', escapeshellarg($link), escapeshellarg($target));
    exec($cmd, $output, $code);
    if ($code === 0 || is_dir($link)) {
        echo "OK: Storage link created\n";
    } else {
        // Fallback: copy directory contents
        echo "Trying directory copy...\n";
        // Just note the issue
        echo "WARN: Cannot create symlink, files exist at: $target\n";
    }
} else {
    echo "OK: Storage link already exists\n";
}

// Verify
$testFile = $link . '/uploads/1/screenshot';
if (is_dir($testFile)) {
    $files = glob($testFile . '/*.png');
    echo "Uploaded PNGs: " . count($files) . "\n";
} else {
    echo "Upload directory not found: $testFile\n";
    // List what exists
    foreach (glob($target . '/uploads/*/*/*.png') as $f) {
        echo "  File exists at: " . str_replace(__DIR__, '', $f) . "\n";
    }
}

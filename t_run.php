<?php
$linkPath = __DIR__ . '/public/storage';
$targetPath = __DIR__ . '/storage/app/public';

if (!is_dir($linkPath)) {
    if (symlink($targetPath, $linkPath)) {
        echo "Storage link created successfully.\n";
    } elseif (exec('mklink /J "' . $linkPath . '" "' . $targetPath . '" 2>nul', $out, $code), $code === 0) {
        echo "Storage junction created.\n";
    } else {
        echo "Failed to create storage link. Try running as admin.\n";
    }
} else {
    echo "Storage link already exists.\n";
}

// Verify
$testUrl = '/storage/uploads/1/screenshot/';
$files = glob($linkPath . '/uploads/*/*/*.png');
echo "Uploaded files: " . count($files) . "\n";
foreach (array_slice($files, 0, 3) as $f) {
    echo "  " . basename(dirname(dirname($f))) . '/' . basename(dirname($f)) . '/' . basename($f) . "\n";
}

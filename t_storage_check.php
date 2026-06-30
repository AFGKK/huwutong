<?php
echo "storage link exists: " . (is_dir(__DIR__ . '/public/storage') ? "YES" : "NO") . "\n";
$files = glob(__DIR__ . '/storage/app/public/uploads/*/*/*.png');
echo "uploaded files found: " . count($files) . "\n";
foreach (array_slice($files, 0, 3) as $f) {
    echo "  " . str_replace(__DIR__, '', $f) . "\n";
}

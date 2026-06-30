<?php
/**
 * Clear PHP OPcache and Blade view cache
 * Access: http://88.huwutong.com/_opreset.php
 */
if (function_exists('opcache_reset')) {
    $r1 = opcache_reset();
} else {
    $r1 = 'N/A (no OPcache)';
}

// Also clear compiled views
$files = glob(__DIR__ . '/storage/framework/views/*.php');
$count = 0;
foreach ($files as $f) {
    if (is_file($f)) {
        unlink($f);
        $count++;
    }
}

echo "OPcache reset: " . ($r1 ? 'OK' : 'FAILED') . "\n";
echo "Cleared $count compiled views\n";
echo "Done!\n";

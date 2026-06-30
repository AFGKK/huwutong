<?php
$path = 'resources/views/public/product-detail.blade.php';
$c = file_get_contents($path);

// Check for the garbled patterns using hex
$patterns = [
    "\xe9\x8d\x8f\xe9\x87\x8d" => '鍏嶈垂',
    "\xe7\xbb\x9a\xe5\xb0\x8d" => '绔嬪嵆',
];

foreach ($patterns as $hex => $label) {
    $pos = strpos($c, $hex);
    if ($pos !== false) {
        echo "FOUND: $label at position $pos\n";
        $ctx = substr($c, $pos, 30);
        echo "Context: $ctx\n";
        echo "Hex: " . bin2hex($ctx) . "\n\n";
    } else {
        echo "CLEAN: $label not found\n";
    }
}

// Now let's look at what's actually at those positions
// Find all occurrences of the ternary pattern
$searchFor = "price_monthly == 0 ? '";
$pos = 0;
$count = 0;
while (($pos = strpos($c, $searchFor, $pos)) !== false) {
    $count++;
    echo "\nOccurrence $count at position $pos:\n";
    $segment = substr($c, $pos, 80);
    echo "Content: $segment\n";
    echo "Hex: " . bin2hex($segment) . "\n";
    $pos += 1;
}

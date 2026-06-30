<?php
$content = file_get_contents('resources/js/router/index.js');
preg_match_all('/path:\s*\x27([^\x27]+)\x27/', $content, $matches);
$paths = $matches[1];
$counts = array_count_values($paths);
echo "=== 重复的路径 ===\n";
foreach (array_filter($counts, fn($c) => $c > 1) as $path => $count) {
    echo "  $path ($count 次)\n";
}
echo "\n=== 所有唯一路径 (" . count(array_unique($paths)) . " 个) ===\n";
$unique = array_unique($paths);
sort($unique);
echo implode("\n", $unique);

<?php
// Comprehensive scan of ALL public Blade files for remaining corruption
$files = glob('d:/phpEnv/www/88.huwutong.com/resources/views/public/*.blade.php');
$allIssues = [];

foreach ($files as $path) {
    $c = file_get_contents($path);
    $name = basename($path);
    $lines = file($path);
    $issues = [];
    
    // 1. Check for orphaned closing tags (missing <)
    foreach ($lines as $i => $line) {
        $ln = $i + 1;
        // /tagname> without < before it
        if (preg_match('/(?<!<)\/([a-z][a-z0-9]*)\s*>/', $line, $m)) {
            $issues[] = "L$ln: orphan closing tag '/{$m[1]}>'";
        }
        // br> or hr> without <
        if (preg_match('/(?<!<)\b(br|hr)\s*\/?\s*>/i', $line, $m)) {
            $issues[] = "L$ln: orphan tag '{$m[0]}'";
        }
    }
    
    // 2. Check for known wrong characters
    $wrongChars = [
        "\xef\xbc\x80" => '＀ (U+FF00)',
        "\xe2\x86\x80" => 'ↀ (U+2180)',
        "\xef\xbf\xbd" => '� (U+FFFD)',
    ];
    foreach ($wrongChars as $byte => $label) {
        if (strpos($c, $byte) !== false) {
            $issues[] = "Has $label";
        }
    }
    
    // 3. Check for common corrupted Chinese text patterns
    $corrupted = [
        '发這', '验訣', '简秀', '包拀', '框枀', '适用产',
        '月后更新', '未达刀', '加载一', '月新发帮', '得积刀',
        '已经到底产', '筛這',
    ];
    foreach ($corrupted as $pattern) {
        if (strpos($c, $pattern) !== false) {
            $issues[] = "Corrupted text: '$pattern'";
        }
    }
    
    if (!empty($issues)) {
        $allIssues[$name] = $issues;
    }
}

// Print results
if (empty($allIssues)) {
    echo "ALL CLEAN! No issues found.\n";
} else {
    echo "=== Issues found ===\n\n";
    foreach ($allIssues as $name => $issues) {
        echo "$name:\n";
        foreach ($issues as $issue) {
            echo "  - $issue\n";
        }
        echo "\n";
    }
}

echo "\nScan complete.\n";

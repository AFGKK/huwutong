<?php
/**
 * 扫描疑似 UTF-8/GBK 乱码（常见错码汉字）
 */
$suspect = ['濮', '鏈', '鐢', '绯', '鎿', '璇', '鍔', '閰', '娴', '娑', '鍝', '锟', '璐', '璁', '缁', '鍙', '鍏', 'Ã', 'â€', 'ï¼', 'ï¿½'];
$dirs = ['app', 'resources', 'routes', 'tests', 'database', 'config', 'docs', 'scripts'];
$found = [];

foreach ($dirs as $dir) {
    if (! is_dir(__DIR__.'/../'.$dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../'.$dir));
    foreach ($it as $f) {
        if (! $f->isFile()) {
            continue;
        }
        $ext = strtolower($f->getExtension());
        if (! in_array($ext, ['php', 'vue', 'js', 'md', 'json', 'sql', 'txt', 'yml', 'yaml', 'blade.php'], true)) {
            continue;
        }
        $path = $f->getPathname();
        $c = file_get_contents($path);
        if ($c === false) {
            continue;
        }
        foreach ($suspect as $p) {
            if (str_contains($c, $p)) {
                $rel = str_replace(__DIR__.'/../', '', $path);
                $found[$rel] = ($found[$rel] ?? 0) + substr_count($c, $p);
                break;
            }
        }
    }
}

ksort($found);
echo '=== 疑似乱码文件: '.count($found)." ===\n";
foreach ($found as $file => $hits) {
    echo "  {$file} (hits~{$hits})\n";
}

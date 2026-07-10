<?php
$dirs = ['app', 'resources', 'routes', 'tests', 'database', 'config', 'docs'];
$bad = [];
foreach ($dirs as $d) {
    if (! is_dir(__DIR__.'/../'.$d)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../'.$d));
    foreach ($it as $f) {
        if (! $f->isFile()) {
            continue;
        }
        $ext = strtolower($f->getExtension());
        if (! in_array($ext, ['php', 'vue', 'js', 'md', 'json', 'sql'], true)) {
            continue;
        }
        $p = $f->getPathname();
        $b = file_get_contents($p);
        if ($b === false || ! mb_check_encoding($b, 'UTF-8')) {
            $bad[] = str_replace(__DIR__.'/../', '', $p);
        }
    }
}
echo 'Invalid UTF-8 files: '.count($bad)."\n";
foreach ($bad as $p) {
    echo "  {$p}\n";
}

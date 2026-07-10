<?php

$root = dirname(__DIR__).'/tests';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$count = 0;

foreach ($iterator as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR.'Concerns'.DIRECTORY_SEPARATOR)) {
        continue;
    }

    $content = file_get_contents($path);
    if (! str_contains($content, 'use Illuminate\\Foundation\\Testing\\RefreshDatabase;')) {
        continue;
    }

    $new = str_replace(
        'use Illuminate\\Foundation\\Testing\\RefreshDatabase;',
        'use Tests\\Concerns\\RefreshDatabase;',
        $content
    );

    if ($new !== $content) {
        file_put_contents($path, $new);
        $count++;
    }
}

echo "Updated {$count} files\n";

<?php
/**
 * 精确扫描 Api 控制器是否在路由中注册（匹配 ClassName::class）
 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controllerDir = __DIR__.'/../app/Http/Controllers/Api';
$routeFiles = array_merge(
    glob(__DIR__.'/../routes/**/*.php') ?: [],
    glob(__DIR__.'/../routes/api/*.php') ?: [],
    glob(__DIR__.'/../routes/*.php') ?: [],
);
$routeContent = '';
foreach ($routeFiles as $f) {
    $routeContent .= file_get_contents($f) ?: '';
}

$missing = [];
$falsePos = [];
$registered = [];

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($controllerDir, FilesystemIterator::SKIP_DOTS)
);
foreach ($iter as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), 'Controller.php')) {
        continue;
    }
    $class = $file->getBasename('.php');
    $exact = str_contains($routeContent, $class.'::class');
    $substr = str_contains($routeContent, $class);

    if ($exact) {
        $registered[] = $class;
    } else {
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen(__DIR__.'/../app/Http/Controllers/')));
        $missing[] = $rel;
        if ($substr) {
            $falsePos[] = $class;
        }
    }
}

sort($missing);
sort($registered);
sort($falsePos);

echo "=== 精确 API 控制器路由扫描 (ClassName::class) ===\n";
echo '已注册: '.count($registered).'  未注册: '.count($missing)."\n\n";

if ($falsePos) {
    echo "--- 子串误报（旧扫描会误判为已注册）---\n";
    foreach ($falsePos as $c) {
        echo "  ⚠ {$c}\n";
    }
    echo "\n";
}

echo "--- 未注册控制器 ---\n";
foreach ($missing as $c) {
    echo "  • {$c}\n";
}

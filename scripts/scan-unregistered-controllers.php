<?php
/**
 * 扫描 Api 控制器是否已注册路由
 * 使用正则提取路由文件中出现的 Controller::class，避免子串误报
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

preg_match_all('/(\w+Controller)::class/', $routeContent, $matches);
$registeredInRoutes = array_unique($matches[1] ?? []);

// AdminPageDataController 通过 registerRoutes() 静态方法注册
if (str_contains($routeContent, 'AdminPageDataController::registerRoutes')) {
    $registeredInRoutes[] = 'AdminPageDataController';
}

$missing = [];
$registered = [];

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($controllerDir, FilesystemIterator::SKIP_DOTS)
);
foreach ($iter as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), 'Controller.php')) {
        continue;
    }
    $class = $file->getBasename('.php');
    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen(__DIR__.'/../app/Http/Controllers/')));
    if (in_array($class, $registeredInRoutes, true)) {
        $registered[] = $rel;
    } else {
        $missing[] = $rel;
    }
}

sort($missing);
sort($registered);

echo "=== API 控制器路由注册扫描 ===\n";
echo '已注册: '.count($registered).'  未注册: '.count($missing)."\n\n";

$priority = ['WorkflowController', 'EnterpriseSsoController', 'LicenseHealthController', 'DeviceTrustController'];
echo "--- 重点控制器 ---\n";
foreach ($priority as $p) {
    $found = false;
    foreach ($registered as $r) {
        if (str_ends_with($r, $p.'.php')) {
            $found = true;
            break;
        }
    }
    echo ($found ? '  ✅' : '  ❌')." {$p}\n";
}

echo "\n--- 未注册控制器 ---\n";
foreach ($missing as $c) {
    echo "  • {$c}\n";
}

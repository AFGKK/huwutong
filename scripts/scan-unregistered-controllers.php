<?php
/**
 * 扫描 Api 控制器是否已注册路由
 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controllerDir = __DIR__.'/../app/Http/Controllers/Api';
$routeFiles = glob(__DIR__.'/../routes/**/*.php') ?: [];
$routeFiles = array_merge($routeFiles, glob(__DIR__.'/../routes/*.php') ?: []);
$routeContent = '';
foreach ($routeFiles as $f) {
    $routeContent .= file_get_contents($f) ?: '';
}

$missing = [];
$registered = [];
foreach (glob($controllerDir.'/*Controller.php') as $file) {
    $class = basename($file, '.php');
    if (str_contains($routeContent, $class)) {
        $registered[] = $class;
    } else {
        $missing[] = $class;
    }
}

sort($missing);
sort($registered);

echo "=== API 控制器路由注册扫描 ===\n";
echo '已注册: '.count($registered).'  未注册: '.count($missing)."\n\n";

$priority = ['AirGappedController', 'RevenueDashboardController', 'ApmController', 'WafController', 'SlaController'];
echo "--- 重点未注册控制器 ---\n";
foreach ($priority as $p) {
    if (in_array($p, $missing, true)) {
        echo "  ❌ {$p}\n";
    } elseif (in_array($p, $registered, true)) {
        echo "  ✅ {$p}\n";
    }
}

echo "\n--- 全部未注册 (前 40) ---\n";
foreach (array_slice($missing, 0, 40) as $c) {
    echo "  • {$c}\n";
}
if (count($missing) > 40) {
    echo '  ... 另有 '.(count($missing) - 40)." 个\n";
}

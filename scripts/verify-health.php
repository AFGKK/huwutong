<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach (['/api/health/live', '/api/health/ready', '/api/health/status'] as $path) {
    try {
        $response = $app->handle(Illuminate\Http\Request::create($path, 'GET'));
        echo "{$path}: HTTP {$response->getStatusCode()}\n";
        echo substr($response->getContent(), 0, 400)."\n\n";
    } catch (Throwable $e) {
        echo "{$path}: EXCEPTION {$e->getMessage()}\n";
        echo $e->getFile().':'.$e->getLine()."\n\n";
    }
}

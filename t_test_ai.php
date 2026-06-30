<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $service = app(App\Services\LlmService::class);
    $resp = $service->chat(
        [['role' => 'user', 'content' => '你好，回复一句话介绍一下你自己']],
        ['model' => 'deepseek-chat', 'provider' => 'deepseek']
    );
    echo "Response: " . json_encode($resp, JSON_UNESCAPED_UNICODE) . "\n";
} catch (\Throwable $e) {
    echo get_class($e) . ': ' . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    // Simulate login request
    $request = \Illuminate\Http\Request::create('/api/login', 'POST', [
        'email' => 'admin@huwutong.com',
        'password' => 'admin123',
    ]);
    
    $controller = app(\App\Http\Controllers\Api\AuthController::class);
    $response = $controller->login($request);
    $data = $response->getData();
    
    echo "Login API Response:\n";
    echo "  success: " . ($data->success ?? 'false') . "\n";
    if (isset($data->data->token)) {
        echo "  token: " . substr($data->data->token, 0, 20) . "...\n";
        echo "  user: " . ($data->data->user->name ?? '') . "\n";
    }
    if (isset($data->error)) {
        echo "  error: " . json_encode($data->error) . "\n";
    }
    echo "\n✅ 登录 API 工作正常\n";
} catch (\Throwable $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
}

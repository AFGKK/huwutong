<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test creating an AI friend
try {
    $controller = app(\App\Http\Controllers\Api\AiFriendController::class);
    
    $request = \Illuminate\Http\Request::create('/api/ai-friends/platform', 'POST', [
        'name' => '测试客服助手',
        'category' => '客服支持',
        'welcome_message' => '你好，我是测试客服助手！',
        'model_provider' => 'deepseek',
        'model_name' => 'deepseek-chat',
        'visibility' => 'global',
    ]);
    $request->setUserResolver(function() { return \App\Models\User::find(1); });
    $request->headers->set('Content-Type', 'multipart/form-data');
    
    $resp = $controller->store($request);
    $data = $resp->getData();
    echo "Create AI friend response:\n";
    print_r($data);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

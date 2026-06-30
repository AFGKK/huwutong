<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $controller = app(\App\Http\Controllers\Api\ChatController::class);
    $request = \Illuminate\Http\Request::create('/api/chat/send', 'POST', [
        'message' => '你好',
        'session_id' => 'test-session-1',
    ]);
    // Set auth user
    $request->setUserResolver(function() {
        return \App\Models\User::find(1);
    });
    
    $resp = $controller->send($request);
    $data = $resp->getData();
    echo "Response:\n";
    print_r($data);
} catch (\Throwable $e) {
    echo get_class($e) . ': ' . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

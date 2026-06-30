<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test the chat stream endpoint directly  
try {
    $controller = app(\App\Http\Controllers\Api\UserChatController::class);
    $request = \Illuminate\Http\Request::create('/api/user-chat/ai-conversation', 'POST');
    $request->setUserResolver(function() { 
        return \App\Models\User::find(1); 
    });
    
    $resp = $controller->createAIConversation($request);
    $data = $resp->getData();
    echo "AI Conversation Response:\n";
    print_r($data);
} catch (\Throwable $e) {
    echo get_class($e) . ': ' . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

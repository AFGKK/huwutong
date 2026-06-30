<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use App\Models\UserConversation;
$convs = UserConversation::where('name', 'like', '%传输%')->orWhere('name', 'like', '%文件%')->get(['id','name','type']);
echo json_encode($convs->toArray());

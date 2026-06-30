<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test AI friend avatar upload
$controller = app(\App\Http\Controllers\Api\AiFriendController::class);
$request = \Illuminate\Http\Request::create('/api/ai-friends/upload-avatar', 'POST');
$request->setUserResolver(function() { return \App\Models\User::find(1); });

$imgPath = tempnam(sys_get_temp_dir(), 'ai_avatar') . '.png';
$img = imagecreate(100, 100);
imagecolorallocate($img, 0, 102, 255);
imagepng($img, $imgPath);
imagedestroy($img);

$request->files->set('avatar', new \Illuminate\Http\UploadedFile(
    $imgPath, 'avatar.png', 'image/png', null, true
));

try {
    $resp = $controller->uploadAvatar($request);
    $data = $resp->getData();
    echo "Upload response:\n";
    print_r($data);
    echo "\n✅ AI friend avatar upload works!\n";
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} finally {
    @unlink($imgPath);
}

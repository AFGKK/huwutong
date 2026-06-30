<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test avatar upload API directly
$controller = app(\App\Http\Controllers\Api\AuthController::class);
$request = \Illuminate\Http\Request::create('/api/avatar/upload', 'POST');
$request->setUserResolver(function() { return \App\Models\User::find(1); });

// Create a dummy image for testing
$imgPath = tempnam(sys_get_temp_dir(), 'test_avatar') . '.png';
$img = imagecreate(100, 100);
imagecolorallocate($img, 255, 0, 0);
imagepng($img, $imgPath);
imagedestroy($img);

$request->files->set('avatar', new \Illuminate\Http\UploadedFile(
    $imgPath, 'test.png', 'image/png', null, true
));

try {
    $resp = $controller->uploadAvatar($request);
    $data = $resp->getData();
    echo "Response:\n";
    print_r($data);
    echo "\n✅ Avatar upload API works!\n";
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
} finally {
    @unlink($imgPath);
}

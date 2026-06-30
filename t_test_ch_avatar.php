<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test channel avatar upload
$channel = \Illuminate\Support\Facades\DB::table('channels')->first();
if (!$channel) { echo "No channel found\n"; exit; }

$controller = app(\App\Http\Controllers\Api\ChannelController::class);
$request = \Illuminate\Http\Request::create('/api/channels/upload-avatar', 'POST', [
    'channel_id' => $channel->id,
]);
$request->setUserResolver(function() { return \App\Models\User::find(1); });
// Also set auth for auth() helper
\Illuminate\Support\Facades\Auth::shouldUse('sanctum');
auth()->setUser(\App\Models\User::find(1));

// Create test image
$imgPath = tempnam(sys_get_temp_dir(), 'ch_avatar') . '.png';
$img = imagecreate(64, 64);
imagecolorallocate($img, 0, 150, 255);
imagepng($img, $imgPath);
imagedestroy($img);

$request->files->set('avatar', new \Illuminate\Http\UploadedFile($imgPath, 'channel.png', 'image/png', null, true));

try {
    $resp = $controller->uploadAvatar($request);
    $data = $resp->getData();
    echo "Upload response:\n";
    print_r($data);
    // Check channel was updated
    $updated = \Illuminate\Support\Facades\DB::table('channels')->where('id', $channel->id)->first();
    echo "\nChannel avatar: " . ($updated->avatar ?: 'EMPTY') . "\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    @unlink($imgPath);
}

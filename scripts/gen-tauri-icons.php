<?php
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$dir = __DIR__.'/../desktop/tauri/src-tauri/icons';
if (! is_dir($dir)) {
    mkdir($dir, 0755, true);
}
foreach (['32x32.png', '128x128.png', '128x128@2x.png', 'icon.png'] as $file) {
    file_put_contents($dir.'/'.$file, $png);
}
echo "icons ok\n";

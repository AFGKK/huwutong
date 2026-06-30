<?php
$path = 'D:\\phpEnv\\www\\88.huwutong.com\\resources\\views\\public\\product-detail.blade.php';
$content = file_get_contents($path);
// The corruption is: UTF-8 bytes read as Windows-1252
// Fix: interpret as Windows-1252, output as UTF-8
$bytes = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
// But this may also corrupt non-Chinese text. Better approach:
// Try to detect the actual encoding
$detected = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
echo "Detected encoding: " . $detected . "\n";
// Try the double-encoding fix
$fixed = mb_convert_encoding($content, 'UTF-8', 'LATIN1');
file_put_contents($path . '.fixed', $fixed);
echo "Fixed file written to: " . $path . ".fixed\n";
echo "Original size: " . strlen($content) . "\n";
echo "Fixed size: " . strlen($fixed) . "\n";

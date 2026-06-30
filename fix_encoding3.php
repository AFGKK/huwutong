<?php
$path = 'D:\\phpEnv\\www\\88.huwutong.com\\resources\\views\\public\\product-detail.blade.php';
$content = file_get_contents($path);

// Try different encoding conversions
$encodings = ['GBK', 'GB2312', 'BIG5', 'EUC-CN', 'CP936'];
foreach ($encodings as $enc) {
    $converted = @mb_convert_encoding($content, 'UTF-8', $enc);
    $checks = ['收藏', '分享', '对比', '导航'];
    $found = false;
    foreach ($checks as $c) {
        if (mb_strpos($converted, $c) !== false) $found = true;
    }
    echo "$enc: " . ($found ? 'WORKS!' : 'no') . " (size: " . strlen($converted) . ")\n";
    if ($found) {
        file_put_contents($path . '.fixed_' . $enc, $converted);
    }
}

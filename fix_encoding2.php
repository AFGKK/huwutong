<?php
$path = 'D:\\phpEnv\\www\\88.huwutong.com\\resources\\views\\public\\product-detail.blade.php';
$content = file_get_contents($path);

// The garbled text was created by: 
// 1. Original UTF-8 Chinese → saved as Windows-1252 (bytes interpreted wrongly)
// 2. Those bytes saved again, now as UTF-8 (different Chinese characters)
// Fix: reverse step 1: convert from UTF-8 to Windows-1252 to get original bytes
// Then UTF-8 decode those bytes
$utf8bytes = $content;
$win1252 = mb_convert_encoding($utf8bytes, 'Windows-1252', 'UTF-8');
// Now win1252 has the original UTF-8 byte stream (but as a Win-1252 string)
// Convert it back to proper UTF-8
$fixed = mb_convert_encoding($win1252, 'UTF-8', 'Windows-1252');

file_put_contents($path . '.fixed2', $fixed);
echo "Fixed2 size: " . strlen($fixed) . "\n";

// Verify - check if some known strings are fixed
$checks = ['收藏', '分享', '对比', '暗色模式', '导航栏', '商品详情', '评价'];
foreach ($checks as $check) {
    $found = mb_strpos($fixed, $check) !== false;
    echo "Contains '$check': " . ($found ? 'YES' : 'NO') . "\n";
}

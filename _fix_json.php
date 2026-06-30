<?php
$c = file_get_contents('d:/phpEnv/www/88.huwutong.com/resources/views/public/landing.blade.php');
$pos = strpos($c, '多平台 SDK');
if ($pos !== false) {
    $ctx = substr($c, $pos, 30);
    echo "Context: $ctx\n";
    echo "Hex: " . bin2hex($ctx) . "\n";
    
    // Check if followed by fullwidth space
    $next = substr($c, $pos + 12, 5);
    echo "After SDK: " . bin2hex($next) . "\n";
    
    // Fix it
    $c = substr_replace($c, '多平台 SDK"', $pos, 13); // 13 = length of "多平台 SDK　,
    file_put_contents($f = 'd:/phpEnv/www/88.huwutong.com/resources/views/public/landing.blade.php', $c);
    echo "Fixed!\n";
}

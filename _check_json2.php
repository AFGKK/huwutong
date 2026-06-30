<?php
$c = file_get_contents('d:/phpEnv/www/88.huwutong.com/resources/views/public/landing.blade.php');
$pos = strpos($c, '平台 SDK');
if ($pos !== false) {
    $ctx = substr($c, $pos, 20);
    echo "Found at $pos:\n";
    echo "Text: " . bin2hex($ctx) . "\n";
    echo "Chars: ";
    for ($i = 0; $i < strlen($ctx); $i++) {
        echo dechex(ord($ctx[$i])) . ' ';
    }
    echo "\n";
}

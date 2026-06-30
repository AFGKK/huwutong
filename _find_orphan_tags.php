<?php
$files = glob('resources/views/public/*.blade.php');

// Search for orphaned closing tags (without < before them)
// These are indicators that < was corrupted to something else
$closingTags = ['/h2>', '/h3>', '/h4>', '/p>', '/span>', '/div>', '/li>', '/a>', 
                '/button>', '/strong>', '/section>', '/header>', '/footer>',
                '/nav>', '/main>', '/table>', '/tr>', '/td>', '/th>',
                '/form>', '/label>', '/title>', '/head>', '/body>', '/html>'];

foreach ($files as $path) {
    $c = file_get_contents($path);
    $name = basename($path);
    $found = false;
    
    foreach ($closingTags as $tag) {
        $pos = 0;
        while (($pos = strpos($c, $tag, $pos)) !== false) {
            // Check if preceded by <
            if ($pos > 0) {
                $prev = substr($c, $pos - 1, 1);
                // Also check for # or other corruption chars
                if ($prev !== '<' && $prev !== '>') {
                    $before = substr($c, max(0, $pos - 3), 6);
                    $hex = bin2hex($before);
                    // Skip if it's part of a Blade comment or just a string
                    if (!preg_match('/[\'"]/', $before)) {
                        echo "$name: orphan '$tag' at pos $pos, prev='$prev' ctx='$before' hex=$hex\n";
                        $found = true;
                    }
                }
            }
            $pos += strlen($tag);
        }
    }
}

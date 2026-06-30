<?php
$files = [
    'about' => '/about',
    'blog' => '/blog/test-slug',
    'cms-page' => '/terms',
    'compare-products' => '/compare-products',
    'compare' => '/compare',
    'contact' => '/contact',
    'cookie-policy' => '/cookie-policy',
    'hall-of-fame' => '/hall-of-fame',
    'help' => '/help/test-slug',
    'landing' => '/',
    'license-query' => '/license/query',
    'pricing' => '/pricing',
    'privacy' => '/privacy',
    'product-detail' => '/products/test-slug',
    'products' => '/products',
    'quickstart' => '/docs/quickstart',
    'sdk' => '/docs/sdk',
    'security-policy' => '/security-policy',
    'terms' => '/terms',
];

foreach ($files as $name => $path) {
    $url = 'http://88.huwutong.com' . $path;
    echo "Checking $name ($url)... ";
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $html = @file_get_contents($url, false, $ctx);
    if ($html !== false) {
        $len = strlen($html);
        echo "OK ($len bytes)\n";
        $outfile = "storage/recovered_{$name}.html";
        file_put_contents($outfile, $html);
    } else {
        echo "FAILED\n";
    }
}

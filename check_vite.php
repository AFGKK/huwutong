<?php
echo "hot: " . (file_exists(__DIR__ . '/public/hot') ? 'EXISTS' : 'NOT FOUND') . "\n";
echo "manifest: " . (file_exists(__DIR__ . '/public/build/manifest.json') ? 'EXISTS' : 'NOT FOUND') . "\n";
echo "assets dir: " . (is_dir(__DIR__ . '/public/build/assets') ? 'EXISTS' : 'NOT FOUND') . "\n";
echo "APP_ENV: " . (getenv('APP_ENV') ?: 'not set') . "\n";
echo "APP_DEBUG: " . (getenv('APP_DEBUG') ?: 'not set') . "\n";

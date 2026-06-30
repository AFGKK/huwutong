<?php
// Run migration via web
chdir(__DIR__ . '/..');
$output = shell_exec('php artisan migrate --path=database/migrations/2026_06_24_000001_add_floating_button_to_cookie_consent.php 2>&1');
echo "<pre>" . htmlspecialchars($output ?? 'No output') . "</pre>";

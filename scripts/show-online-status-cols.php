<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (\Illuminate\Support\Facades\Schema::hasTable('user_online_statuses')) {
    foreach (DB::select('SHOW COLUMNS FROM user_online_statuses') as $c) {
        echo "  {$c->Field}\n";
    }
} else {
    echo "table missing\n";
}

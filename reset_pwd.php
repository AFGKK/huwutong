<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$u = App\Models\User::first();
$u->password = bcrypt('12345678');
$u->save();
echo "Password reset to 12345678 for: " . $u->email . "\n";

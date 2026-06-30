<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = \App\Models\User::find(1);
if ($user) {
    $user->assignRole('super-admin');
    echo "Role assigned to {$user->name}\n";
    echo "Roles: " . json_encode($user->getRoleNames()) . "\n";
} else {
    echo "User not found\n";
}

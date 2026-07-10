<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// 手动设置团队/租户ID上下文，绕过 Spatie 团队限制
app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(1);

$user = \App\Models\User::find(1);
if ($user) {
    $user->assignRole('super-admin');
    echo "Role assigned to {$user->name}\n";
    echo "Roles: " . json_encode($user->getRoleNames()) . "\n";
} else {
    echo "User not found\n";
}

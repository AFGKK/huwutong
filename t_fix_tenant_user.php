<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Fix user tenant_id
$updated = \Illuminate\Support\Facades\DB::table('users')
    ->where('id', 1)
    ->whereNull('tenant_id')
    ->update(['tenant_id' => 1]);

echo "Updated user 1 tenant_id: " . ($updated ? 'Yes' : 'No already set') . "\n";

// Also fix other users
$updated2 = \Illuminate\Support\Facades\DB::table('users')
    ->whereIn('id', [2, 3])
    ->whereNull('tenant_id')
    ->update(['tenant_id' => 1]);

echo "Updated users 2,3 tenant_id: " . ($updated2 ? 'Yes' : 'No') . "\n";

// Verify
$users = \Illuminate\Support\Facades\DB::table('users')->select('id', 'email', 'tenant_id')->get();
foreach ($users as $u) {
    echo "  User {$u->id}: {$u->email} -> tenant_id={$u->tenant_id}\n";
}

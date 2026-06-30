<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Users:\n";
foreach (DB::table('users')->get(['id', 'email', 'tenant_id', 'status']) as $u) {
    $roles = DB::table('model_has_roles')
        ->where('model_id', $u->id)
        ->where('model_type', 'App\\Models\\User')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->pluck('roles.name')
        ->implode(', ');
    echo "  #{$u->id} {$u->email} tenant={$u->tenant_id} roles=" . ($roles ?: '(none)') . "\n";
}

echo "\norders columns (payment_extra, expires_at):\n";
$cols = DB::select("SHOW COLUMNS FROM orders WHERE Field IN ('payment_extra','expires_at','cancelled_at','transaction_id')");
foreach ($cols as $c) echo "  {$c->Field} ({$c->Type})\n";

$couponOrderId = DB::select("SHOW COLUMNS FROM coupon_redemptions WHERE Field = 'order_id'");
echo "\ncoupon_redemptions.order_id: " . (count($couponOrderId) ? 'EXISTS' : 'MISSING') . "\n";
echo "migrations ran: " . DB::table('migrations')->count() . "\n";

echo "\nNon-standard site_settings types:\n";
foreach (DB::table('site_settings')->whereNotIn('type', ['text','textarea','image','color','switch','select','password'])->get() as $s) {
    echo "  [{$s->group}] {$s->key} ({$s->type})\n";
}

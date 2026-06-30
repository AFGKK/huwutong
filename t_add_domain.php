<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Columns:\n";
print_r(\Illuminate\Support\Facades\Schema::getColumnListing('custom_domains'));

// Add 88.huwutong.com as default domain
$exists = \Illuminate\Support\Facades\DB::table('custom_domains')->where('domain', '88.huwutong.com')->exists();
if (!$exists) {
    \Illuminate\Support\Facades\DB::table('custom_domains')->insert([
        'tenant_id' => 1,
        'domain' => '88.huwutong.com',
        'verified' => true,
        'is_active' => true,
        'status' => 'active',
        'verified_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "Inserted 88.huwutong.com\n";
} else {
    echo "Already exists\n";
}

$domains = \Illuminate\Support\Facades\DB::table('custom_domains')->get();
echo "Total domains: " . $domains->count() . "\n";
foreach ($domains as $d) {
    echo "  - {$d->domain} (verified: " . ($d->is_verified ? 'Y' : 'N') . ", default: " . ($d->is_default ? 'Y' : 'N') . ")\n";
}

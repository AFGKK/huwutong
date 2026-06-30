<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

$fixed = 0;

// Fix workflow_definitions.created_by
if (Schema::hasTable('workflow_definitions') && !Schema::hasColumn('workflow_definitions', 'created_by')) {
    Schema::table('workflow_definitions', function (Blueprint $t) {
        $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('description');
    });
    echo "✅ Added workflow_definitions.created_by\n";
    $fixed++;
}

// Also check for any other common missing columns
$checks = [
    ['table' => 'promotions', 'column' => 'id', 'type' => 'bigIncrements'],
];

foreach ($checks as $c) {
    if (!Schema::hasTable($c['table'])) {
        echo "⚠️ {$c['table']} table doesn't exist\n";
    }
}

echo "\nFixed {$fixed} issues.\n";

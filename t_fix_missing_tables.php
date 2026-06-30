<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

$tables = ['agent_groups', 'conversation_tags'];
foreach ($tables as $table) {
    if (!Schema::hasTable($table)) {
        Schema::create($table, function (Blueprint $t) use ($table) {
            $t->id();
            if ($table === 'agent_groups') {
                $t->string('name', 100);
                $t->string('slug', 100)->unique();
                $t->text('description')->nullable();
                $t->boolean('is_active')->default(true);
                $t->unsignedInteger('sort_order')->default(0);
            } else {
                $t->string('name', 100);
                $t->string('slug', 100)->unique();
                $t->string('color', 20)->nullable();
                $t->unsignedInteger('sort_order')->default(0);
            }
            $t->timestamps();
        });
        echo "Created: $table\n";
    } else {
        echo "Exists: $table\n";
    }
}

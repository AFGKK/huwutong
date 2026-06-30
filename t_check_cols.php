<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$sm = \Illuminate\Support\Facades\Schema::getConnection()->getDoctrineSchemaManager();
$cols = $sm->listTableColumns('conversation_messages');
foreach ($cols as $col) {
    echo $col->getName() . " (" . $col->getType()->getName() . ")\n";
}

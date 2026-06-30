<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

\Illuminate\Support\Facades\DB::statement("ALTER TABLE conversation_messages MODIFY COLUMN sender_type VARCHAR(20) DEFAULT 'user'");
echo "Fixed sender_type default\n";

\Illuminate\Support\Facades\DB::statement("ALTER TABLE conversation_messages MODIFY COLUMN message_type VARCHAR(30) DEFAULT 'text'");
echo "Fixed message_type default\n";

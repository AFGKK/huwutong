<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== LLM Configs ===\n";
$configs = \Illuminate\Support\Facades\DB::table('ai_friend_llm_configs')->get();
foreach ($configs as $c) {
    echo "  profile_id={$c->ai_friend_id}, provider={$c->provider}, model={$c->model_name}\n";
}

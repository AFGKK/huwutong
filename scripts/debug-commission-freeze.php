<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $user = App\Models\User::factory()->create();
    $account = App\Models\EarningsAccount::factory()->create(['user_id' => $user->id]);
    $agent = App\Models\Agent::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $settlement = App\Models\CommissionSettlement::factory()->create([
        'agent_id' => $agent->id,
        'commission_amount' => 500.00,
        'status' => 'pending',
    ]);

    app(App\Services\CommissionRiskGuard::class)->freezeCommission($account, $settlement);
    echo "OK freeze\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

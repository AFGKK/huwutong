<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Illuminate\Support\Facades\DB::beginTransaction();

$user = App\Models\User::factory()->create();
$account = App\Models\EarningsAccount::factory()->create(['user_id' => $user->id]);
$agent = App\Models\Agent::factory()->create(['user_id' => $user->id, 'status' => 'active']);

try {
    $settlement = App\Models\CommissionSettlement::factory()->create([
        'agent_id' => $agent->id,
        'commission_amount' => 500.00,
        'status' => 'pending',
    ]);
    echo "settlement id={$settlement->id}\n";
} catch (Throwable $e) {
    echo "settlement ERROR: " . $e->getMessage() . "\n";
}

try {
    app(App\Services\CommissionRiskGuard::class)->freezeCommission($account, $settlement);
    $fresh = $account->fresh();
    echo "OK pending={$fresh->pending_balance}\n";
} catch (Throwable $e) {
    echo "freeze ERROR: " . $e->getMessage() . "\n";
}

Illuminate\Support\Facades\DB::rollBack();

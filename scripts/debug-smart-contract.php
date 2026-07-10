<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tenant = App\Models\Tenant::factory()->create();
$license = App\Models\License::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);

$contract = App\Models\LicenseContract::factory()->create([
    'tenant_id' => $tenant->id,
    'conditions' => [
        ['type' => 'license_status', 'operator' => 'eq', 'field' => 'status', 'value' => 'active', 'label' => '状态活跃'],
    ],
]);

$service = new App\Services\SmartContractService(new App\Services\ContractConditionEngine());

try {
    $service->assignContract([
        'tenant_id' => $tenant->id,
        'contract_id' => $contract->id,
        'assignable_type' => 'App\\Models\\License',
        'assignable_id' => $license->id,
    ]);

    $service->evaluateForEntity('App\\Models\\License', $license->id, ['status' => 'active']);
    echo 'logs: ' . App\Models\LicenseContractEvaluationLog::count() . PHP_EOL;
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}

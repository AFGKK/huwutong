<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Illuminate\Support\Facades\Config::set('database.default', 'pgsql');

$tenant = App\Models\Tenant::factory()->create();
$license = App\Models\License::factory()->create(['tenant_id' => $tenant->id]);

$event = new App\Events\LicenseStatusChanged(
    $license,
    'active',
    'expired',
    'test',
);

try {
    app(App\Services\AuditService::class)->licenseStatusChanged(
        tenantId: $event->license->tenant_id,
        licenseId: $event->license->id,
        licenseKey: $event->license->license_key,
        oldStatus: $event->oldStatus,
        newStatus: $event->newStatus,
        reason: $event->reason,
        userId: $event->operatorId ?? auth()->id(),
    );
    echo "Audit OK, logs count: " . App\Models\Log::count() . PHP_EOL;
} catch (Throwable $e) {
    echo "Audit ERROR: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}

try {
    App\Models\Notification::create([
        'tenant_id' => $license->tenant_id,
        'customer_id' => $license->customer_id,
        'type' => 'status_change',
        'title' => 'test',
        'content' => 'test content',
        'payload' => ['license_id' => $license->id],
    ]);
    echo "Notification OK, count: " . App\Models\Notification::count() . PHP_EOL;
} catch (Throwable $e) {
    echo "Notification ERROR: " . $e->getMessage() . PHP_EOL;
}

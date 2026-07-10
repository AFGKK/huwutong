<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Simulate testing env
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

Illuminate\Support\Facades\Config::set('database.default', 'pgsql');

$tenant = App\Models\Tenant::factory()->create();
$license = App\Models\License::factory()->create([
    'tenant_id' => $tenant->id,
    'status' => App\Enums\LicenseStatus::Active->value,
    'expires_at' => now()->addDays(7),
]);

$event = new App\Events\LicenseStatusChanged(
    $license,
    App\Enums\LicenseStatus::Active->value,
    App\Enums\LicenseStatus::Expired->value,
);

Illuminate\Support\Facades\Log::listen(function ($log) {
    echo "[LOG {$log->level}] {$log->message}\n";
    if (!empty($log->context)) {
        echo json_encode($log->context, JSON_UNESCAPED_UNICODE) . "\n";
    }
});

app(App\Services\EventBus::class)->dispatch($event);

echo 'logs: ' . App\Models\Log::count() . PHP_EOL;
echo 'notifications: ' . App\Models\Notification::count() . PHP_EOL;

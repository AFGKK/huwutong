<?php
/**
 * Quick PostgreSQL migration status check
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== PostgreSQL Status Check ===\n\n";
echo "DB_CONNECTION: " . config('database.default') . "\n";
echo "DB_DATABASE: " . config('database.connections.' . config('database.default') . '.database') . "\n\n";

try {
    $driver = DB::connection()->getDriverName();
    echo "Driver: $driver\n";

    $tables = DB::select("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'");
    echo "Tables: " . count($tables) . "\n";

    $migrations = DB::table('migrations')->count();
    echo "Migrations ran: $migrations\n\n";

    $extensions = DB::select('SELECT extname, extversion FROM pg_extension ORDER BY extname');
    echo "Extensions:\n";
    foreach ($extensions as $ext) {
        echo "  - {$ext->extname} ({$ext->extversion})\n";
    }
    $hasPgvector = collect($extensions)->contains(fn ($e) => $e->extname === 'vector');
    echo $hasPgvector ? "\npgvector: installed\n" : "\npgvector: NOT installed\n";

    echo "\nKey table counts:\n";
    $keys = ['users', 'tenants', 'roles', 'permissions', 'role_has_permissions', 'model_has_roles',
        'licenses', 'customers', 'devices', 'products', 'product_skus', 'site_settings',
        'pricing_plans', 'product_categories', 'coupons', 'subscriptions'];
    foreach ($keys as $tbl) {
        try {
            $cnt = DB::table($tbl)->count();
            echo "  $tbl: $cnt\n";
        } catch (Throwable $e) {
            echo "  $tbl: ERROR - {$e->getMessage()}\n";
        }
    }

    echo "\nHealth check:\n";
    try {
        $resp = Illuminate\Support\Facades\Http::timeout(5)->get(rtrim(config('app.url'), '/').'/api/health/live');
        echo "  /api/health/live (HTTP): {$resp->status()}\n";
    } catch (Throwable $e) {
        echo "  /api/health/live (HTTP): FAIL - {$e->getMessage()}\n";
    }
    try {
        $kernelResp = app()->handle(Illuminate\Http\Request::create('/api/health/ready', 'GET'));
        echo "  /api/health/ready (CLI): HTTP {$kernelResp->getStatusCode()}\n";
    } catch (Throwable $e) {
        echo "  /api/health/ready (CLI): FAIL - {$e->getMessage()}\n";
    }
} catch (Throwable $e) {
    echo "ERROR: {$e->getMessage()}\n";
    exit(1);
}

echo "\nDone.\n";

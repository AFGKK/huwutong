<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$admin = App\Models\User::where('email', 'admin@huwutong.com')->first() ?? App\Models\User::first();
$token = $admin->createToken('t', ['admin', 'super-admin'])->plainTextToken;

$paths = [
    '/api/admin/workflows/dashboard',
    '/api/admin/workflows/definitions',
    '/api/admin/workflows/stats',
    '/api/admin/workflows/instances',
    '/api/ecommerce/refunds/stats',
    '/api/ecommerce/refunds',
    '/api/admin/auto-renewal/dashboard',
    '/api/admin/sla-probes/dashboard',
    '/api/affiliate/campaigns',
    '/api/affiliate/dashboard',
];

foreach ($paths as $path) {
    $req = Illuminate\Http\Request::create($path, 'GET');
    $req->headers->set('Authorization', 'Bearer '.$token);
    $req->headers->set('Accept', 'application/json');
    if ($admin->tenant_id) {
        $req->headers->set('X-Tenant-Id', (string) $admin->tenant_id);
    }
    $res = $app->handle($req);
    echo $path.' → '.$res->getStatusCode()."\n";
}

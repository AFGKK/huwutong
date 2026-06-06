<?php

use App\Models\Tenant;

if (! function_exists('current_tenant')) {
    function current_tenant(): ?Tenant
    {
        return app()->bound(Tenant::class) ? app(Tenant::class) : null;
    }
}

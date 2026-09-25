<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    private function isAdmin(User $user): bool
    {
        return $this->isSuperAdmin($user) || $user->hasRole('admin');
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $this->isSuperAdmin($user)
            || ($this->isAdmin($user) && (int) $user->tenant_id === (int) $tenant->id);
    }

    public function create(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->view($user, $tenant);
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function toggleStatus(User $user, Tenant $tenant): bool
    {
        return $this->isSuperAdmin($user);
    }
}

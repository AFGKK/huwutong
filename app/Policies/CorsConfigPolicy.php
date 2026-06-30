<?php

namespace App\Policies;

use App\Models\CorsConfig;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CorsConfigPolicy
{
    use HandlesAuthorization;

    public function before(User $user): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('cors.view');
    }

    public function view(User $user, CorsConfig $corsConfig): bool
    {
        return $user->hasPermissionTo('cors.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('cors.create');
    }

    public function update(User $user, CorsConfig $corsConfig): bool
    {
        return $user->hasPermissionTo('cors.update');
    }

    public function delete(User $user, CorsConfig $corsConfig): bool
    {
        return $user->hasPermissionTo('cors.delete');
    }

    public function test(User $user): bool
    {
        return $user->hasPermissionTo('cors.view');
    }
}

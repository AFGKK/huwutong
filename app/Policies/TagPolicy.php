<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TagPolicy
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
        return $user->hasPermissionTo('tag.view');
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->hasPermissionTo('tag.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('tag.create');
    }

    public function update(User $user, Tag $tag): bool
    {
        if ($tag->is_system && ! $user->hasPermissionTo('super-admin')) {
            return false;
        }
        return $user->hasPermissionTo('tag.update');
    }

    public function delete(User $user, Tag $tag): bool
    {
        if ($tag->is_system) {
            return false;
        }
        return $user->hasPermissionTo('tag.delete');
    }
}

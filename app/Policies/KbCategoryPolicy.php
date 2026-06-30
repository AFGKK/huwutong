<?php

namespace App\Policies;

use App\Models\KbCategory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class KbCategoryPolicy
{
    use HandlesAuthorization;

    public function before(User $user): ?bool
    {
        return true;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('kb-category.view');
    }

    public function view(User $user, KbCategory $category): bool
    {
        return $user->hasPermissionTo('kb-category.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('kb-category.create');
    }

    public function update(User $user, KbCategory $category): bool
    {
        return $user->hasPermissionTo('kb-category.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('kb-category.delete');
    }
}

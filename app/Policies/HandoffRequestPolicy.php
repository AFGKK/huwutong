<?php

namespace App\Policies;

use App\Models\HandoffRequest;
use App\Models\User;

class HandoffRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('super-admin') || $user->hasRole('客服');
    }

    public function view(User $user, HandoffRequest $handoff): bool
    {
        return $user->hasRole('admin') || $user->hasRole('super-admin')
            || $user->hasRole('客服')
            || $handoff->user_id === $user->id
            || $handoff->assigned_to === $user->id;
    }

    public function create(User $user): bool
    {
        return true; // 任何认证用户都可以发起转接
    }

    public function update(User $user, HandoffRequest $handoff): bool
    {
        return $user->hasRole('admin') || $user->hasRole('super-admin')
            || $user->hasRole('客服');
    }
}

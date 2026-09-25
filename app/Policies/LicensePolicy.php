<?php

namespace App\Policies;

use App\Models\License;
use App\Models\User;

class LicensePolicy
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
        // 登录用户可列自己的；管理员可列租户内全部
        return true;
    }

    public function view(User $user, License $license): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if ($this->isAdmin($user) && (int) $user->tenant_id === (int) $license->tenant_id) {
            return true;
        }

        $customerId = $user->customer?->id;
        return $customerId && (int) $license->customer_id === (int) $customerId;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, License $license): bool
    {
        return $this->isAdmin($user)
            && ($this->isSuperAdmin($user) || (int) $user->tenant_id === (int) $license->tenant_id);
    }

    public function delete(User $user, License $license): bool
    {
        return $this->update($user, $license);
    }
}

<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    private function isAdmin(User $user): bool
    {
        return $this->isSuperAdmin($user) || $user->hasRole('admin');
    }

    /**
     * 管理员可以查看所有用户列表
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * 管理员可以查看用户详情（同一租户或 super-admin）
     */
    public function view(User $user, User $target): bool
    {
        return $this->isSuperAdmin($user)
            || ($this->isAdmin($user) && $user->tenant_id === $target->tenant_id);
    }

    /**
     * 管理员可以创建用户
     */
    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * 管理员可以编辑用户
     */
    public function update(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return true;
        }
        if ($this->isSuperAdmin($target) && !$this->isSuperAdmin($user)) {
            return false;
        }
        return $this->isSuperAdmin($user)
            || ($this->isAdmin($user) && $user->tenant_id === $target->tenant_id);
    }

    /**
     * 管理员可以删除/禁用用户
     */
    public function delete(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return false;
        }
        if ($this->isSuperAdmin($target) && !$this->isSuperAdmin($user)) {
            return false;
        }
        return $this->isSuperAdmin($user)
            || ($this->isAdmin($user) && $user->tenant_id === $target->tenant_id);
    }

    /**
     * 管理员可以重置用户密码
     */
    public function resetPassword(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return false;
        }
        return $this->isSuperAdmin($user)
            || ($this->isAdmin($user) && $user->tenant_id === $target->tenant_id);
    }

    /**
     * 管理员可以禁用/启用用户
     */
    public function toggleStatus(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return false;
        }
        if ($this->isSuperAdmin($target) && !$this->isSuperAdmin($user)) {
            return false;
        }
        return $this->isSuperAdmin($user)
            || ($this->isAdmin($user) && $user->tenant_id === $target->tenant_id);
    }
}

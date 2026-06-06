<?php

namespace App\Models\Concerns;

use App\Http\Middleware\GlobalResourceWhitelist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * 多租户模型 Trait
 *
 * 自动为所有查询添加 tenant_id 过滤（基于当前认证用户）。
 * 超级管理员可以通过 X-Tenant-Id 头查看其他租户数据。
 * 全局共享表（白名单）不强制添加 tenant_id 过滤。
 *
 * 使用方式：
 *   class License extends Model {
 *       use \App\Models\Concerns\BelongsToTenant;
 *   }
 */
trait BelongsToTenant
{
    /**
     * 启动 Trait
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $model = $builder->getModel();
            $table = $model->getTable();

            // 白名单表不添加 tenant_id 过滤
            if (GlobalResourceWhitelist::isTableWhitelisted($table)) {
                return;
            }

            $tenantId = self::resolveTenantId();
            if ($tenantId !== null) {
                $builder->where(
                    $table . '.tenant_id',
                    $tenantId,
                );
            }
        });
    }

    /**
     * 获取当前租户 ID
     */
    protected static function resolveTenantId(): ?int
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        // 超级管理员可通过 X-Tenant-Id 头切换租户
        if ($user->hasRole('super-admin') && request()->header('X-Tenant-Id')) {
            return (int) request()->header('X-Tenant-Id');
        }

        return $user->tenant_id;
    }

    /**
     * 不考虑租户隔离的查询（超级管理员用）
     */
    public function scopeWithoutTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    /**
     * 按特定租户查询
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->withoutGlobalScope('tenant')
            ->where((new static)->getTable() . '.tenant_id', $tenantId);
    }
}

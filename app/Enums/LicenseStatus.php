<?php

namespace App\Enums;

enum LicenseStatus: string
{
    case Pending = 'pending';           // 待激活
    case Active = 'active';             // 活跃
    case Suspended = 'suspended';       // 已挂起
    case Frozen = 'frozen';             // 已冻结（风控/法律）
    case Expired = 'expired';           // 已过期
    case Revoked = 'revoked';          // 已撤销
    case Refunded = 'refunded';        // 已退款
    case Blacklisted = 'blacklisted';  // 已黑名单

    /**
     * 严格状态转移矩阵
     * key = 当前状态, value = 允许转移到的状态列表
     */
    public static function transitions(): array
    {
        return [
            self::Pending->value => [
                self::Active->value,
                self::Revoked->value,
                self::Blacklisted->value,
            ],
            self::Active->value => [
                self::Suspended->value,
                self::Frozen->value,
                self::Expired->value,
                self::Revoked->value,
                self::Refunded->value,
                self::Blacklisted->value,
            ],
            self::Suspended->value => [
                self::Active->value,
                self::Frozen->value,
                self::Expired->value,
                self::Revoked->value,
                self::Refunded->value,
                self::Blacklisted->value,
            ],
            self::Frozen->value => [
                self::Active->value,
                self::Suspended->value,
                self::Expired->value,
                self::Revoked->value,
                self::Refunded->value,
                self::Blacklisted->value,
            ],
            self::Expired->value => [
                self::Active->value,    // 续费后恢复
                self::Revoked->value,
                self::Refunded->value,
                self::Blacklisted->value,
            ],
            self::Revoked->value => [
                self::Blacklisted->value,
            ],
            self::Refunded->value => [
                self::Blacklisted->value,
            ],
            self::Blacklisted->value => [],  // 终态，不可转移
        ];
    }

    /**
     * 判断是否允许从当前状态转移到目标状态
     */
    public function canTransitionTo(self $target): bool
    {
        $allowed = self::transitions()[$this->value] ?? [];

        return in_array($target->value, $allowed, true);
    }

    /**
     * 判断是否为"活动"状态（可正常使用）
     */
    public function isActiveState(): bool
    {
        return in_array($this, [
            self::Active,
        ], true);
    }

    /**
     * 判断是否为"可用"状态（可进行验证）
     */
    public function isUsable(): bool
    {
        return in_array($this, [
            self::Active,
            self::Suspended,
            self::Frozen,
        ], true);
    }

    /**
     * 判断是否为"可激活"状态（pending 和 usable 状态）
     */
    public function isActivable(): bool
    {
        return $this === self::Pending || $this->isUsable();
    }

    /**
     * 判断是否为"终态"（不可再变）
     */
    public function isTerminal(): bool
    {
        return $this === self::Blacklisted;
    }
}

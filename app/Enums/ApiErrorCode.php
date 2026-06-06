<?php

namespace App\Enums;

enum ApiErrorCode: string
{
    // ─── 通用错误（1000-1099） ───
    case UNKNOWN_ERROR = 'UNKNOWN_ERROR';
    case VALIDATION_ERROR = 'VALIDATION_ERROR';
    case UNAUTHORIZED = 'UNAUTHORIZED';
    case FORBIDDEN = 'FORBIDDEN';
    case NOT_FOUND = 'NOT_FOUND';
    case METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';
    case TOO_MANY_REQUESTS = 'TOO_MANY_REQUESTS';
    case INTERNAL_ERROR = 'INTERNAL_ERROR';
    case SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';

    // ─── 认证错误（1100-1199） ───
    case AUTH_FAILED = 'AUTH_FAILED';
    case AUTH_TOKEN_EXPIRED = 'AUTH_TOKEN_EXPIRED';
    case AUTH_TOKEN_INVALID = 'AUTH_TOKEN_INVALID';
    case AUTH_TOKEN_BLACKLISTED = 'AUTH_TOKEN_BLACKLISTED';

    // ─── License 错误（2000-2099） ───
    case LICENSE_NOT_FOUND = 'LICENSE_NOT_FOUND';
    case LICENSE_EXPIRED = 'LICENSE_EXPIRED';
    case LICENSE_NOT_ACTIVATABLE = 'LICENSE_NOT_ACTIVATABLE';
    case LICENSE_ALREADY_ACTIVE = 'LICENSE_ALREADY_ACTIVE';
    case LICENSE_REVOKED = 'LICENSE_REVOKED';
    case LICENSE_BLACKLISTED = 'LICENSE_BLACKLISTED';
    case LICENSE_INVALID_KEY = 'LICENSE_INVALID_KEY';
    case LICENSE_TYPE_MISMATCH = 'LICENSE_TYPE_MISMATCH';
    case LICENSE_INVALID_TRANSITION = 'LICENSE_INVALID_TRANSITION';

    // ─── 设备错误（2100-2199） ───
    case DEVICE_LIMIT_EXCEEDED = 'DEVICE_LIMIT_EXCEEDED';
    case DEVICE_BLACKLISTED = 'DEVICE_BLACKLISTED';
    case DEVICE_FINGERPRINT_INVALID = 'DEVICE_FINGERPRINT_INVALID';
    case DEVICE_VIRTUAL_ENVIRONMENT = 'DEVICE_VIRTUAL_ENVIRONMENT';

    // ─── Trial 错误（2200-2299） ───
    case TRIAL_NOT_ALLOWED = 'TRIAL_NOT_ALLOWED';
    case TRIAL_ALREADY_USED = 'TRIAL_ALREADY_USED';
    case TRIAL_COOLDOWN = 'TRIAL_COOLDOWN';
    case TRIAL_EXPIRED = 'TRIAL_EXPIRED';
    case CONVERSION_FAILED = 'CONVERSION_FAILED';

    // ─── 订阅/计费错误（2300-2399） ───
    case SUBSCRIPTION_EXPIRED = 'SUBSCRIPTION_EXPIRED';
    case PAYMENT_FAILED = 'PAYMENT_FAILED';
    case INVOICE_OVERDUE = 'INVOICE_OVERDUE';

    // ─── 权限错误（2400-2499） ───
    case PERMISSION_DENIED = 'PERMISSION_DENIED';
    case ROLE_NOT_FOUND = 'ROLE_NOT_FOUND';
    case ROLE_ALREADY_ASSIGNED = 'ROLE_ALREADY_ASSIGNED';

    // ─── 租户错误（2500-2599） ───
    case TENANT_INACTIVE = 'TENANT_INACTIVE';
    case TENANT_SUSPENDED = 'TENANT_SUSPENDED';
    case TENANT_QUOTA_EXCEEDED = 'TENANT_QUOTA_EXCEEDED';

    /**
     * 获取错误码对应的 HTTP 状态码
     */
    public function httpStatus(): int
    {
        return match ($this) {
            // 4xx Client Errors
            self::VALIDATION_ERROR => 422,
            self::UNAUTHORIZED,
            self::AUTH_FAILED,
            self::AUTH_TOKEN_EXPIRED,
            self::AUTH_TOKEN_INVALID,
            self::AUTH_TOKEN_BLACKLISTED => 401,
            self::FORBIDDEN,
            self::PERMISSION_DENIED => 403,
            self::NOT_FOUND,
            self::LICENSE_NOT_FOUND => 404,
            self::METHOD_NOT_ALLOWED => 405,
            self::TOO_MANY_REQUESTS => 429,

            // License errors
            self::LICENSE_EXPIRED,
            self::LICENSE_NOT_ACTIVATABLE,
            self::LICENSE_INVALID_KEY,
            self::LICENSE_INVALID_TRANSITION,
            self::DEVICE_LIMIT_EXCEEDED,
            self::TRIAL_NOT_ALLOWED,
            self::TRIAL_ALREADY_USED,
            self::TRIAL_COOLDOWN,
            self::CONVERSION_FAILED => 422,

            // 5xx Server Errors
            self::INTERNAL_ERROR,
            self::SERVICE_UNAVAILABLE => 500,

            default => 400,
        };
    }

    /**
     * 获取中文描述
     */
    public function message(): string
    {
        return match ($this) {
            // 通用
            self::UNKNOWN_ERROR => '未知错误',
            self::VALIDATION_ERROR => '参数验证失败',
            self::UNAUTHORIZED => '未授权访问',
            self::FORBIDDEN => '权限不足',
            self::NOT_FOUND => '资源不存在',
            self::METHOD_NOT_ALLOWED => '请求方法不允许',
            self::TOO_MANY_REQUESTS => '请求过于频繁',
            self::INTERNAL_ERROR => '服务器内部错误',
            self::SERVICE_UNAVAILABLE => '服务暂不可用',

            // 认证
            self::AUTH_FAILED => '邮箱/手机号或密码错误',
            self::AUTH_TOKEN_EXPIRED => 'Token 已过期',
            self::AUTH_TOKEN_INVALID => 'Token 无效',
            self::AUTH_TOKEN_BLACKLISTED => 'Token 已被列入黑名单',

            // License
            self::LICENSE_NOT_FOUND => 'License Key 不存在',
            self::LICENSE_EXPIRED => 'License 已过期',
            self::LICENSE_NOT_ACTIVATABLE => 'License 当前状态不允许操作',
            self::LICENSE_ALREADY_ACTIVE => 'License 已激活',
            self::LICENSE_REVOKED => 'License 已被撤销',
            self::LICENSE_BLACKLISTED => 'License 已被列入黑名单',
            self::LICENSE_INVALID_KEY => 'License Key 格式无效',
            self::LICENSE_TYPE_MISMATCH => 'License 类型不匹配',
            self::LICENSE_INVALID_TRANSITION => '不允许的状态转移',

            // 设备
            self::DEVICE_LIMIT_EXCEEDED => '设备数量已达上限',
            self::DEVICE_BLACKLISTED => '设备已被列入黑名单',
            self::DEVICE_FINGERPRINT_INVALID => '设备指纹无效',
            self::DEVICE_VIRTUAL_ENVIRONMENT => '虚拟环境不允许激活',

            // Trial
            self::TRIAL_NOT_ALLOWED => '不允许创建试用',
            self::TRIAL_ALREADY_USED => '该客户已使用过试用',
            self::TRIAL_COOLDOWN => '试用冷却期未过',
            self::TRIAL_EXPIRED => '试用已过期',
            self::CONVERSION_FAILED => '转正失败',

            // 订阅/计费
            self::SUBSCRIPTION_EXPIRED => '订阅已过期',
            self::PAYMENT_FAILED => '支付失败',
            self::INVOICE_OVERDUE => '发票已逾期',

            // 权限
            self::PERMISSION_DENIED => '无操作权限',
            self::ROLE_NOT_FOUND => '角色不存在',
            self::ROLE_ALREADY_ASSIGNED => '角色已分配',

            // 租户
            self::TENANT_INACTIVE => '租户未激活',
            self::TENANT_SUSPENDED => '租户已被停用',
            self::TENANT_QUOTA_EXCEEDED => '租户配额超限',
        };
    }
}

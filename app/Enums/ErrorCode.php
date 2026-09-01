<?php

namespace App\Enums;

/**
 * M2-34: 互物通标准化错误码枚举
 *
 * 命名规范：{DOMAIN}_{SPECIFIC_ERROR}
 * 前缀规则：
 *   AUTH_     — 认证与授权
 *   LICENSE_  — License 相关
 *   API_      — API Key / 调用
 *   MFA_      — 多因素认证
 *   SSO_      — 单点登录
 *   RATE_     — 频率限制
 *   SIG_      — 签名验证
 *   BILL_     — 计费与订阅
 *   INV_      — 发票
 *   TAX_      — 税务
 *   DEVICE_   — 设备
 *   DOMAIN_   — 自定义域名
 *   WEBHOOK_  — Webhook
 *   LLM_      — LLM / AI
 *   VALID_    — 验证类（Validation）
 *   NOT_FOUND — 资源不存在
 *   SYS_      — 系统内部
 *   SDK_      — SDK 通用
 *   ACT_      — 激活 / 离线
 *   FF_       — Feature Flag
 *   TENANT_   — 租户
 *   TAG_      — 标签
 *   CUSTOMER_ — 客户
 *   ERRCODE_  — 错误码系统自身
 */
enum ErrorCode: string
{
    // ─── 认证与授权 ──────────────────────────────────────────────
    case AUTH_FAILED = 'AUTH_FAILED';
    case INVALID_TOKEN = 'INVALID_TOKEN';
    case TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    case ACCOUNT_DISABLED = 'ACCOUNT_DISABLED';
    case ACCOUNT_PENDING_DELETION = 'ACCOUNT_PENDING_DELETION';
    case ACCOUNT_DELETED = 'ACCOUNT_DELETED';
    case INVITE_REQUIRED = 'INVITE_REQUIRED';
    case INVALID_PASSWORD = 'INVALID_PASSWORD';
    case PASSWORD_REUSED = 'PASSWORD_REUSED';
    case TOO_FREQUENT = 'TOO_FREQUENT';
    case CANNOT_REVOKE_CURRENT = 'CANNOT_REVOKE_CURRENT';
    case ALREADY_VERIFIED = 'ALREADY_VERIFIED';
    case ALREADY_CONSENTED = 'ALREADY_CONSENTED';
    case PENDING_REQUEST = 'PENDING_REQUEST';
    case ALREADY_BOUND = 'ALREADY_BOUND';
    case UNBIND_FAILED = 'UNBIND_FAILED';
    case INVALID_CODE = 'INVALID_CODE';
    case INVITE_EXPIRED = 'INVITE_EXPIRED';
    case INVITE_USED = 'INVITE_USED';
    case LOGIN_EXPIRED = 'LOGIN_EXPIRED';

    // ─── SDK 通用 ──────────────────────────────────────────────
    case SDK_VERSION_DEPRECATED = 'SDK_VERSION_DEPRECATED';
    case SDK_UNSUPPORTED = 'SDK_UNSUPPORTED';
    case SDK_HEARTBEAT_INTERVAL = 'SDK_HEARTBEAT_INTERVAL';

    // ─── License ────────────────────────────────────────────────
    case LICENSE_NOT_FOUND = 'LICENSE_NOT_FOUND';
    case LICENSE_EXPIRED = 'LICENSE_EXPIRED';
    case LICENSE_NOT_ACTIVE = 'LICENSE_NOT_ACTIVE';
    case LICENSE_SUSPENDED = 'LICENSE_SUSPENDED';
    case LICENSE_REVOKED = 'LICENSE_REVOKED';
    case LICENSE_PENDING_APPROVAL = 'LICENSE_PENDING_APPROVAL';
    case LICENSE_ACTIVATION_LIMIT = 'LICENSE_ACTIVATION_LIMIT';
    case LICENSE_DEVICE_LIMIT = 'LICENSE_DEVICE_LIMIT';
    case LICENSE_FINGERPRINT_MISMATCH = 'LICENSE_FINGERPRINT_MISMATCH';
    case LICENSE_FILE_INVALID = 'LICENSE_FILE_INVALID';
    case LICENSE_FILE_TAMPERED = 'LICENSE_FILE_TAMPERED';
    case LICENSE_GRACE_PERIOD = 'LICENSE_GRACE_PERIOD';
    case LICENSE_IN_MAINTENANCE = 'LICENSE_IN_MAINTENANCE';
    case LICENSE_ALREADY_ACTIVATED = 'LICENSE_ALREADY_ACTIVATED';
    case LICENSE_TIME_RESTRICTED = 'LICENSE_TIME_RESTRICTED';
    case LICENSE_IP_RESTRICTED = 'LICENSE_IP_RESTRICTED';

    // ─── 激活 / 离线 ────────────────────────────────────────────
    case ACT_SIGNATURE_INVALID = 'ACT_SIGNATURE_INVALID';
    case ACT_CERTIFICATE_EXPIRED = 'ACT_CERTIFICATE_EXPIRED';
    case ACT_NO_CERTIFICATE = 'ACT_NO_CERTIFICATE';
    case ACT_OFFLINE_EXPIRED = 'ACT_OFFLINE_EXPIRED';

    // ─── API Key ──────────────────────────────────────────────
    case API_KEY_REQUIRED = 'API_KEY_REQUIRED';
    case API_KEY_INVALID = 'API_KEY_INVALID';
    case API_KEY_EXPIRED = 'API_KEY_EXPIRED';
    case API_KEY_IP_MISMATCH = 'API_KEY_IP_MISMATCH';
    case API_KEY_INSUFFICIENT = 'API_KEY_INSUFFICIENT';
    case API_KEY_METHOD_DENIED = 'API_KEY_METHOD_DENIED';
    case API_KEY_ENDPOINT_DENIED = 'API_KEY_ENDPOINT_DENIED';
    case API_KEY_QUOTA_EXCEEDED = 'API_KEY_QUOTA_EXCEEDED';
    case API_KEY_REVOKED = 'API_KEY_REVOKED';
    case API_KEY_SUSPENDED = 'API_KEY_SUSPENDED';
    case MAX_KEYS_REACHED = 'MAX_KEYS_REACHED';

    // ─── MFA ──────────────────────────────────────────────────
    case MFA_CODE_INVALID = 'MFA_CODE_INVALID';
    case MFA_NOT_ENABLED = 'MFA_NOT_ENABLED';
    case MFA_ALREADY_ENABLED = 'MFA_ALREADY_ENABLED';
    case MFA_BACKUP_USED = 'MFA_BACKUP_USED';
    case MFA_REQUIRED = 'MFA_REQUIRED';

    // ─── SSO ──────────────────────────────────────────────────
    case SSO_PROVIDER_INACTIVE = 'SSO_PROVIDER_INACTIVE';
    case SSO_PROVIDER_NOT_FOUND = 'SSO_PROVIDER_NOT_FOUND';
    case SSO_ASSERTION_INVALID = 'SSO_ASSERTION_INVALID';

    // ─── 频率限制 ──────────────────────────────────────────────
    case RATE_LIMITED = 'RATE_LIMITED';
    case RATE_BURST_LIMITED = 'RATE_BURST_LIMITED';
    case RATE_GLOBAL_LIMITED = 'RATE_GLOBAL_LIMITED';
    case RATE_CONCURRENCY_LIMITED = 'RATE_CONCURRENCY_LIMITED';

    // ─── 签名 ────────────────────────────────────────────────
    case SIG_MISSING = 'SIG_MISSING';
    case SIG_INVALID = 'SIG_INVALID';
    case SIG_TIMESTAMP_INVALID = 'SIG_TIMESTAMP_INVALID';
    case SIG_TIMESTAMP_EXPIRED = 'SIG_TIMESTAMP_EXPIRED';
    case SIG_NONCE_REUSED = 'SIG_NONCE_REUSED';
    case SIG_HEADER_MISSING = 'SIG_HEADER_MISSING';
    case SIG_BODY_MISMATCH = 'SIG_BODY_MISMATCH';

    // ─── 非重复请求 ──────────────────────────────────────────
    case IDEMPOTENT_KEY_MISSING = 'IDEMPOTENT_KEY_MISSING';
    case IDEMPOTENT_KEY_REPLAYED = 'IDEMPOTENT_KEY_REPLAYED';
    case IDEMPOTENT_IN_PROGRESS = 'IDEMPOTENT_IN_PROGRESS';

    // ─── 计费与订阅 ──────────────────────────────────────────
    case BILL_SUBSCRIPTION_EXPIRED = 'BILL_SUBSCRIPTION_EXPIRED';
    case BILL_PAYMENT_FAILED = 'BILL_PAYMENT_FAILED';
    case BILL_PAYMENT_DECLINED = 'BILL_PAYMENT_DECLINED';
    case BILL_INSUFFICIENT_FUNDS = 'BILL_INSUFFICIENT_FUNDS';
    case BILL_REFUND_FAILED = 'BILL_REFUND_FAILED';
    case BILL_PLAN_NOT_FOUND = 'BILL_PLAN_NOT_FOUND';
    case BILL_PLAN_UNAVAILABLE = 'BILL_PLAN_UNAVAILABLE';
    case BILL_COUPON_INVALID = 'BILL_COUPON_INVALID';
    case BILL_COUPON_EXPIRED = 'BILL_COUPON_EXPIRED';
    case BILL_COUPON_USED = 'BILL_COUPON_USED';
    case BILL_TRIAL_EXPIRED = 'BILL_TRIAL_EXPIRED';
    case BILL_TRIAL_NOT_AVAILABLE = 'BILL_TRIAL_NOT_AVAILABLE';
    case BILL_GRACE_ENDED = 'BILL_GRACE_ENDED';

    // ─── 发票 ────────────────────────────────────────────────
    case INV_NOT_FOUND = 'INV_NOT_FOUND';
    case INV_ALREADY_PAID = 'INV_ALREADY_PAID';
    case INV_ALREADY_CANCELLED = 'INV_ALREADY_CANCELLED';
    case INV_OVERDUE = 'INV_OVERDUE';
    case INV_REFUNDED = 'INV_REFUNDED';

    // ─── 税务 ────────────────────────────────────────────────
    case TAX_RATE_NOT_FOUND = 'TAX_RATE_NOT_FOUND';
    case TAX_COUNTRY_NOT_SUPPORTED = 'TAX_COUNTRY_NOT_SUPPORTED';
    case TAX_EXEMPTION_INVALID = 'TAX_EXEMPTION_INVALID';
    case TAX_CALCULATION_FAILED = 'TAX_CALCULATION_FAILED';

    // ─── 设备 ────────────────────────────────────────────────
    case DEVICE_NOT_FOUND = 'DEVICE_NOT_FOUND';
    case DEVICE_LIMIT_EXCEEDED = 'DEVICE_LIMIT_EXCEEDED';
    case DEVICE_FINGERPRINT_MISMATCH = 'DEVICE_FINGERPRINT_MISMATCH';
    case DEVICE_TRUST_EXPIRED = 'DEVICE_TRUST_EXPIRED';
    case DEVICE_REVOKED = 'DEVICE_REVOKED';
    case DEVICE_REGION_BLOCKED = 'DEVICE_REGION_BLOCKED';

    // ─── 自定义域名 ──────────────────────────────────────────
    case DOMAIN_EXISTS = 'DOMAIN_EXISTS';
    case DOMAIN_NOT_FOUND = 'DOMAIN_NOT_FOUND';
    case DOMAIN_VERIFICATION_FAILED = 'DOMAIN_VERIFICATION_FAILED';
    case DOMAIN_SSL_ERROR = 'DOMAIN_SSL_ERROR';
    case DOMAIN_NOT_CONNECTED = 'DOMAIN_NOT_CONNECTED';

    // ─── Webhook ──────────────────────────────────────────────
    case WEBHOOK_ENDPOINT_INACTIVE = 'WEBHOOK_ENDPOINT_INACTIVE';
    case WEBHOOK_ENDPOINT_NOT_FOUND = 'WEBHOOK_ENDPOINT_NOT_FOUND';
    case WEBHOOK_DELIVERY_FAILED = 'WEBHOOK_DELIVERY_FAILED';
    case WEBHOOK_SIGNATURE_MISMATCH = 'WEBHOOK_SIGNATURE_MISMATCH';
    case WEBHOOK_PAYLOAD_TOO_LARGE = 'WEBHOOK_PAYLOAD_TOO_LARGE';
    case WEBHOOK_RATE_LIMITED = 'WEBHOOK_RATE_LIMITED';
    case WEBHOOK_NO_REPLAYABLE_EVENTS = 'WEBHOOK_NO_REPLAYABLE_EVENTS';
    case WEBHOOK_CIRCUIT_OPEN = 'WEBHOOK_CIRCUIT_OPEN';

    // ─── LLM ──────────────────────────────────────────────────
    case LLM_ERROR = 'LLM_ERROR';
    case LLM_TIMEOUT = 'LLM_TIMEOUT';
    case LLM_RATE_LIMITED = 'LLM_RATE_LIMITED';
    case LLM_INVALID_RESPONSE = 'LLM_INVALID_RESPONSE';
    case LLM_CONTENT_FILTERED = 'LLM_CONTENT_FILTERED';
    case LLM_CONTEXT_OVERFLOW = 'LLM_CONTEXT_OVERFLOW';
    case LLM_PROVIDER_UNAVAILABLE = 'LLM_PROVIDER_UNAVAILABLE';
    case LLM_FALLBACK_ALL_FAILED = 'LLM_FALLBACK_ALL_FAILED';
    case CONNECTION_FAILED = 'CONNECTION_FAILED';

    // ─── 验证 (Validation) ─────────────────────────────────────
    case VALIDATION_ERROR = 'VALIDATION_ERROR';
    case VALIDATION_INVALID_INPUT = 'VALIDATION_INVALID_INPUT';
    case VALIDATION_MISSING_FIELD = 'VALIDATION_MISSING_FIELD';
    case VALIDATION_INVALID_FORMAT = 'VALIDATION_INVALID_FORMAT';
    case VALIDATION_BUSINESS_RULE = 'VALIDATION_BUSINESS_RULE';

    // ─── 资源不存在 ──────────────────────────────────────────
    case NOT_FOUND = 'NOT_FOUND';
    case RESOURCE_DELETED = 'RESOURCE_DELETED';

    // ─── 通用权限 ──────────────────────────────────────────
    case UNAUTHORIZED = 'UNAUTHORIZED';
    case FORBIDDEN = 'FORBIDDEN';
    case FORBIDDEN_IP = 'FORBIDDEN_IP';
    case FORBIDDEN_REGION = 'FORBIDDEN_REGION';
    case FORBIDDEN_MAINTENANCE = 'FORBIDDEN_MAINTENANCE';
    case SYSTEM_ROLE = 'SYSTEM_ROLE';
    case PERMISSION_DENIED = 'PERMISSION_DENIED';

    // ─── 节流与断路器 ──────────────────────────────────────
    case CIRCUIT_OPEN = 'CIRCUIT_OPEN';
    case CIRCUIT_HALF_OPEN = 'CIRCUIT_HALF_OPEN';
    case BODY_TOO_LARGE = 'BODY_TOO_LARGE';
    case PAYLOAD_TOO_LARGE = 'PAYLOAD_TOO_LARGE';

    // ─── 系统内部 ──────────────────────────────────────────
    case SYS_INTERNAL_ERROR = 'SYS_INTERNAL_ERROR';
    case SYS_MAINTENANCE = 'SYS_MAINTENANCE';
    case SYS_DEPENDENCY_FAILURE = 'SYS_DEPENDENCY_FAILURE';
    case SYS_DATABASE_ERROR = 'SYS_DATABASE_ERROR';
    case SYS_CACHE_ERROR = 'SYS_CACHE_ERROR';
    case SYS_QUEUE_ERROR = 'SYS_QUEUE_ERROR';
    case SYS_STORAGE_ERROR = 'SYS_STORAGE_ERROR';
    case SYS_CONFIG_ERROR = 'SYS_CONFIG_ERROR';
    case SYS_SERVICE_UNAVAILABLE = 'SYS_SERVICE_UNAVAILABLE';
    case SYS_UPSTREAM_TIMEOUT = 'SYS_UPSTREAM_TIMEOUT';
    case SYS_THIRD_PARTY_ERROR = 'SYS_THIRD_PARTY_ERROR';

    // ─── 租户 ──────────────────────────────────────────────
    case TENANT_NOT_FOUND = 'TENANT_NOT_FOUND';
    case TENANT_DISABLED = 'TENANT_DISABLED';
    case TENANT_QUOTA_EXCEEDED = 'TENANT_QUOTA_EXCEEDED';

    // ─── Feature Flag ──────────────────────────────────────────
    case FF_NOT_FOUND = 'FF_NOT_FOUND';
    case FF_EVALUATION_ERROR = 'FF_EVALUATION_ERROR';
    case FF_PROVIDER_ERROR = 'FF_PROVIDER_ERROR';

    // ─── 客户 ──────────────────────────────────────────────
    case CUSTOMER_NOT_FOUND = 'CUSTOMER_NOT_FOUND';
    case CUSTOMER_DISABLED = 'CUSTOMER_DISABLED';
    case CUSTOMER_QUOTA_EXCEEDED = 'CUSTOMER_QUOTA_EXCEEDED';
    case CUSTOMER_HEALTH_LOW = 'CUSTOMER_HEALTH_LOW';

    // ─── 标签 ──────────────────────────────────────────────
    case TAG_NOT_FOUND = 'TAG_NOT_FOUND';
    case TAG_NAME_DUPLICATE = 'TAG_NAME_DUPLICATE';

    // ─── 文件 ──────────────────────────────────────────────
    case FILE_TOO_LARGE = 'FILE_TOO_LARGE';
    case FILE_TYPE_NOT_ALLOWED = 'FILE_TYPE_NOT_ALLOWED';
    case FILE_UPLOAD_FAILED = 'FILE_UPLOAD_FAILED';
    case FILE_NOT_FOUND = 'FILE_NOT_FOUND';

    // ─── 账户操作（注销/删除） ──────────────────────────────
    case REQUEST_PROCESSED = 'REQUEST_PROCESSED';
    case COOLING_NOT_OVER = 'COOLING_NOT_OVER';
    case EXECUTION_FAILED = 'EXECUTION_FAILED';

    // ─── API 版本 ──────────────────────────────────────────
    case API_VERSION_UNAVAILABLE = 'API_VERSION_UNAVAILABLE';
    case API_VERSION_DEPRECATED = 'API_VERSION_DEPRECATED';
    case API_VERSION_RETIRED = 'API_VERSION_RETIRED';
    case VERSION_RETIRED = 'VERSION_RETIRED';
    case VERSION_NOT_DEPRECATED = 'VERSION_NOT_DEPRECATED';

    // ─── 错误码系统自身 ──────────────────────────────────
    case ERRCODE_NOT_FOUND = 'ERRCODE_NOT_FOUND';

    // ─── 未知 — 兜底 ──────────────────────────────────────
    case UNKNOWN_ERROR = 'UNKNOWN_ERROR';

    // ─── 测试专用 ──────────────────────────────────────────
    case TEST_ERROR = 'TEST_ERROR';

    /**
     * 获取错误码对应的 HTTP 状态码
     */
    public function httpStatus(): int
    {
        return match ($this) {
            // 401 — 认证
            self::AUTH_FAILED,
            self::INVALID_TOKEN,
            self::TOKEN_EXPIRED,
            self::LOGIN_EXPIRED,
            self::INVALID_CODE,
            self::API_KEY_REQUIRED,
            self::API_KEY_INVALID,
            self::API_KEY_EXPIRED,
            self::API_KEY_REVOKED,
            self::UNAUTHORIZED => 401,

            // 403 — 禁止
            self::ACCOUNT_DISABLED,
            self::ACCOUNT_PENDING_DELETION,
            self::ACCOUNT_DELETED,
            self::LICENSE_NOT_ACTIVE,
            self::LICENSE_SUSPENDED,
            self::LICENSE_REVOKED,
            self::LICENSE_FINGERPRINT_MISMATCH,
            self::API_KEY_IP_MISMATCH,
            self::API_KEY_INSUFFICIENT,
            self::API_KEY_METHOD_DENIED,
            self::API_KEY_ENDPOINT_DENIED,
            self::API_KEY_SUSPENDED,
            self::FORBIDDEN,
            self::FORBIDDEN_IP,
            self::FORBIDDEN_REGION,
            self::FORBIDDEN_MAINTENANCE,
            self::PERMISSION_DENIED,
            self::SYSTEM_ROLE,
            self::MFA_REQUIRED,
            self::TENANT_DISABLED,
            self::CUSTOMER_DISABLED,
            self::CIRCUIT_OPEN,
            self::WEBHOOK_CIRCUIT_OPEN,
            self::SDK_VERSION_DEPRECATED,
            self::SDK_UNSUPPORTED,
            self::SIG_NONCE_REUSED,
            self::ACT_SIGNATURE_INVALID,
            self::ACT_CERTIFICATE_EXPIRED,
            self::LICENSE_IN_MAINTENANCE,
            self::LICENSE_TIME_RESTRICTED,
            self::LICENSE_IP_RESTRICTED,
            self::WEBHOOK_SIGNATURE_MISMATCH,
            self::DEVICE_REGION_BLOCKED,
            self::DOMAIN_NOT_CONNECTED,
            self::FF_EVALUATION_ERROR,
            self::IDEMPOTENT_KEY_REPLAYED,
            self::SYS_MAINTENANCE => 403,

            // 404 — 未找到
            self::LICENSE_NOT_FOUND,
            self::NOT_FOUND,
            self::RESOURCE_DELETED,
            self::DEVICE_NOT_FOUND,
            self::DOMAIN_NOT_FOUND,
            self::WEBHOOK_ENDPOINT_NOT_FOUND,
            self::INV_NOT_FOUND,
            self::BILL_PLAN_NOT_FOUND,
            self::TAX_RATE_NOT_FOUND,
            self::TENANT_NOT_FOUND,
            self::CUSTOMER_NOT_FOUND,
            self::FF_NOT_FOUND,
            self::TAG_NOT_FOUND,
            self::FILE_NOT_FOUND,
            self::SSO_PROVIDER_NOT_FOUND,
            self::ERRCODE_NOT_FOUND,
            self::WEBHOOK_NO_REPLAYABLE_EVENTS,
            self::BILL_COUPON_INVALID => 404,

            // 409 — 冲突
            self::ALREADY_VERIFIED,
            self::ALREADY_CONSENTED,
            self::ALREADY_BOUND,
            self::LICENSE_ALREADY_ACTIVATED,
            self::DOMAIN_EXISTS,
            self::INV_ALREADY_PAID,
            self::INV_ALREADY_CANCELLED,
            self::TAG_NAME_DUPLICATE,
            self::MFA_ALREADY_ENABLED,
            self::IDEMPOTENT_IN_PROGRESS,
            self::VERSION_RETIRED => 409,

            // 422 — 业务验证失败
            self::VALIDATION_ERROR,
            self::VALIDATION_INVALID_INPUT,
            self::VALIDATION_MISSING_FIELD,
            self::VALIDATION_INVALID_FORMAT,
            self::VALIDATION_BUSINESS_RULE,
            self::INVALID_PASSWORD,
            self::PASSWORD_REUSED,
            self::INVITE_REQUIRED,
            self::INVITE_EXPIRED,
            self::INVITE_USED,
            self::CANNOT_REVOKE_CURRENT,
            self::PENDING_REQUEST,
            self::UNBIND_FAILED,
            self::MFA_CODE_INVALID,
            self::MFA_NOT_ENABLED,
            self::MFA_BACKUP_USED,
            self::SSO_PROVIDER_INACTIVE,
            self::SSO_ASSERTION_INVALID,
            self::BILL_COUPON_EXPIRED,
            self::BILL_COUPON_USED,
            self::BILL_TRIAL_NOT_AVAILABLE,
            self::TAX_EXEMPTION_INVALID,
            self::DEVICE_FINGERPRINT_MISMATCH,
            self::DOMAIN_VERIFICATION_FAILED,
            self::LICENSE_PENDING_APPROVAL,
            self::REQUEST_PROCESSED,
            self::COOLING_NOT_OVER,
            self::VERSION_NOT_DEPRECATED,
            self::LICENSE_ACTIVATION_LIMIT,
            self::LICENSE_DEVICE_LIMIT,
            self::MAX_KEYS_REACHED,
            self::FILE_TYPE_NOT_ALLOWED,
            self::CUSTOMER_QUOTA_EXCEEDED,
            self::ACT_NO_CERTIFICATE,
            self::BILL_PLAN_UNAVAILABLE,
            self::IDEMPOTENT_KEY_MISSING,
            self::BILL_TRIAL_EXPIRED,
            self::DEVICE_LIMIT_EXCEEDED,
            self::TENANT_QUOTA_EXCEEDED,
            self::FF_PROVIDER_ERROR,
            self::TAX_COUNTRY_NOT_SUPPORTED,
            self::TAX_CALCULATION_FAILED,
            self::FILE_TOO_LARGE => 422,

            // 429 — 频率限制
            self::RATE_LIMITED,
            self::RATE_BURST_LIMITED,
            self::RATE_GLOBAL_LIMITED,
            self::RATE_CONCURRENCY_LIMITED,
            self::TOO_FREQUENT,
            self::API_KEY_QUOTA_EXCEEDED,
            self::SDK_HEARTBEAT_INTERVAL,
            self::LLM_RATE_LIMITED,
            self::WEBHOOK_RATE_LIMITED,
            self::BILL_INSUFFICIENT_FUNDS => 429,

            // 500 — 服务器错误
            self::SYS_INTERNAL_ERROR,
            self::SYS_DATABASE_ERROR,
            self::SYS_CACHE_ERROR,
            self::SYS_QUEUE_ERROR,
            self::SYS_STORAGE_ERROR,
            self::SYS_CONFIG_ERROR,
            self::SYS_SERVICE_UNAVAILABLE,
            self::SYS_UPSTREAM_TIMEOUT,
            self::SYS_THIRD_PARTY_ERROR,
            self::SYS_DEPENDENCY_FAILURE,
            self::EXECUTION_FAILED,
            self::BILL_PAYMENT_FAILED,
            self::BILL_PAYMENT_DECLINED,
            self::BILL_REFUND_FAILED,
            self::BILL_GRACE_ENDED,
            self::WEBHOOK_DELIVERY_FAILED,
            self::LLM_ERROR,
            self::LLM_TIMEOUT,
            self::LLM_INVALID_RESPONSE,
            self::LLM_CONTENT_FILTERED,
            self::LLM_CONTEXT_OVERFLOW,
            self::LLM_PROVIDER_UNAVAILABLE,
            self::LLM_FALLBACK_ALL_FAILED,
            self::CONNECTION_FAILED,
            self::FILE_UPLOAD_FAILED,
            self::SIG_BODY_MISMATCH,
            self::DOMAIN_SSL_ERROR,
            self::DEVICE_TRUST_EXPIRED,
            self::SIG_TIMESTAMP_EXPIRED,
            self::ACT_OFFLINE_EXPIRED,
            self::WEBHOOK_PAYLOAD_TOO_LARGE,
            self::PAYLOAD_TOO_LARGE,
            self::BODY_TOO_LARGE,
            self::CIRCUIT_HALF_OPEN,
            self::LICENSE_FILE_INVALID,
            self::LICENSE_FILE_TAMPERED,
            self::UNKNOWN_ERROR => 500,

            // 501 — 未实现
            self::TEST_ERROR => 501,

            default => 400,
        };
    }

    /**
     * 获取错误码所属域
     */
    public function domain(): string
    {
        $domain = explode('_', $this->value)[0];

        return match ($domain) {
            'API' => 'API_KEY', // API_KEY_* 和 BILL_* 等用双段前缀
            'BILL', 'INV', 'TAX' => 'BILLING',
            'SYS' => 'SYSTEM',
            'SIG' => 'SIGNATURE',
            'ACT' => 'ACTIVATION',
            'FF' => 'FEATURE_FLAG',
            'RATE' => 'RATE_LIMIT',
            'VALID' => 'VALIDATION',
            'VERSION', 'ERRCODE' => 'API_VERSION',
            default => $domain,
        };
    }

    /**
     * 是否为客户端错误（4xx）
     */
    public function isClientError(): bool
    {
        $status = $this->httpStatus();
        return $status >= 400 && $status < 500;
    }

    /**
     * 是否为服务器错误（5xx）
     */
    public function isServerError(): bool
    {
        return $this->httpStatus() >= 500;
    }

    /**
     * 是否为重试安全的错误（幂等的错误码可以被 SDK 安全重试）
     */
    public function isRetrySafe(): bool
    {
        return match ($this) {
            self::RATE_LIMITED,
            self::RATE_BURST_LIMITED,
            self::RATE_GLOBAL_LIMITED,
            self::RATE_CONCURRENCY_LIMITED,
            self::LLM_TIMEOUT,
            self::LLM_RATE_LIMITED,
            self::LLM_PROVIDER_UNAVAILABLE,
            self::LLM_FALLBACK_ALL_FAILED,
            self::CONNECTION_FAILED,
            self::SYS_SERVICE_UNAVAILABLE,
            self::SYS_UPSTREAM_TIMEOUT,
            self::SYS_THIRD_PARTY_ERROR,
            self::SYS_DATABASE_ERROR,
            self::SYS_CACHE_ERROR,
            self::SYS_QUEUE_ERROR,
            self::CIRCUIT_OPEN,
            self::CIRCUIT_HALF_OPEN,
            self::BILL_PAYMENT_FAILED,
            self::WEBHOOK_DELIVERY_FAILED => true,

            default => false,
        };
    }

    /**
     * 获取所有错误码列表
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * 按域分组获取错误码
     * @return array<string, ErrorCode[]>
     */
    public static function groupedByDomain(): array
    {
        $groups = [];
        foreach (self::cases() as $case) {
            $groups[$case->domain()][] = $case;
        }
        return $groups;
    }
}

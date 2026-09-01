<?php

/**
 * M2-34: 标准化错误码中文消息
 *
 * 每条消息格式支持 :placeholder 替换
 */

return [

    // ─── 认证与授权 ──────────────────────────────────────────────
    'AUTH_FAILED' => '认证失败：账号或密码错误',
    'INVALID_TOKEN' => '无效的令牌，请重新登录',
    'TOKEN_EXPIRED' => '令牌已过期，请重新登录',
    'ACCOUNT_DISABLED' => '账号已被禁用',
    'ACCOUNT_PENDING_DELETION' => '账号正在等待删除',
    'ACCOUNT_DELETED' => '账号已被删除',
    'INVITE_REQUIRED' => '需要有效的邀请码才能注册',
    'INVALID_PASSWORD' => '当前密码错误',
    'PASSWORD_REUSED' => '不能使用最近使用过的密码',
    'TOO_FREQUENT' => '操作太频繁，请稍后再试',
    'CANNOT_REVOKE_CURRENT' => '不能吊销当前会话',
    'ALREADY_VERIFIED' => '已验证，无需重复操作',
    'ALREADY_CONSENTED' => '您已确认过此协议',
    'PENDING_REQUEST' => '有待处理的请求：:message',
    'ALREADY_BOUND' => '该第三方账号已被其他用户绑定',
    'UNBIND_FAILED' => '解绑失败：:message',
    'INVALID_CODE' => '验证码无效或已过期',
    'INVITE_EXPIRED' => '邀请码已过期',
    'INVITE_USED' => '邀请码已被使用',
    'LOGIN_EXPIRED' => '登录状态已过期，请重新登录',

    // ─── SDK 通用 ──────────────────────────────────────────────
    'SDK_VERSION_DEPRECATED' => '当前 SDK 版本已废弃，请升级到最新版本',
    'SDK_UNSUPPORTED' => '当前 SDK 版本不再受支持，请升级后重试',
    'SDK_HEARTBEAT_INTERVAL' => '心跳上报太频繁，请按建议间隔上报',

    // ─── License ────────────────────────────────────────────────
    'LICENSE_NOT_FOUND' => 'License Key 不存在',
    'LICENSE_EXPIRED' => 'License 已过期',
    'LICENSE_NOT_ACTIVE' => 'License 未激活或已停用',
    'LICENSE_SUSPENDED' => 'License 已被暂停',
    'LICENSE_REVOKED' => 'License 已被吊销',
    'LICENSE_PENDING_APPROVAL' => 'License 变更等待审批中',
    'LICENSE_ACTIVATION_LIMIT' => 'License 已达到最大激活次数限制',
    'LICENSE_DEVICE_LIMIT' => 'License 已达到最大设备数限制',
    'LICENSE_FINGERPRINT_MISMATCH' => '设备指纹不匹配',
    'LICENSE_FILE_INVALID' => 'License 文件格式无效',
    'LICENSE_FILE_TAMPERED' => 'License 文件已被篡改',
    'LICENSE_GRACE_PERIOD' => 'License 已过期，处于宽限期（剩余 :days 天）',
    'LICENSE_IN_MAINTENANCE' => 'License 系统正在维护中',
    'LICENSE_ALREADY_ACTIVATED' => 'License 已激活，请勿重复操作',
    'LICENSE_TIME_RESTRICTED' => '当前时段不允许使用该 License',
    'LICENSE_IP_RESTRICTED' => '当前 IP 不在 License 允许范围',

    // ─── 激活 / 离线 ────────────────────────────────────────────
    'ACT_SIGNATURE_INVALID' => '激活签名无效',
    'ACT_CERTIFICATE_EXPIRED' => '离线激活证书已过期',
    'ACT_NO_CERTIFICATE' => '没有可用的离线签名证书',
    'ACT_OFFLINE_EXPIRED' => '离线激活码已过期',

    // ─── API Key ──────────────────────────────────────────────
    'API_KEY_REQUIRED' => '缺少 API Key',
    'API_KEY_INVALID' => 'API Key 无效',
    'API_KEY_EXPIRED' => 'API Key 已过期',
    'API_KEY_IP_MISMATCH' => 'API Key 不允许当前 IP',
    'API_KEY_INSUFFICIENT' => 'API Key 权限不足',
    'API_KEY_METHOD_DENIED' => 'API Key 不允许此 HTTP 方法',
    'API_KEY_ENDPOINT_DENIED' => 'API Key 不允许此端点',
    'API_KEY_QUOTA_EXCEEDED' => 'API Key 请求配额已用完',
    'API_KEY_REVOKED' => 'API Key 已被吊销',
    'API_KEY_SUSPENDED' => 'API Key 已被暂停',
    'MAX_KEYS_REACHED' => '最多可创建 :max 个 API 密钥',

    // ─── MFA ──────────────────────────────────────────────────
    'MFA_CODE_INVALID' => 'MFA 验证码无效',
    'MFA_NOT_ENABLED' => 'MFA 未启用',
    'MFA_ALREADY_ENABLED' => 'MFA 已启用',
    'MFA_BACKUP_USED' => 'MFA 备用码已被使用',
    'MFA_REQUIRED' => '需要 MFA 验证',

    // ─── SSO ──────────────────────────────────────────────────
    'SSO_PROVIDER_INACTIVE' => 'SSO 提供者未启用',
    'SSO_PROVIDER_NOT_FOUND' => 'SSO 提供者未找到',
    'SSO_ASSERTION_INVALID' => 'SSO 断言无效',

    // ─── 频率限制 ──────────────────────────────────────────────
    'RATE_LIMITED' => '请求频率过高，请稍后再试',
    'RATE_BURST_LIMITED' => '突发请求频率过高，请降低请求速度',
    'RATE_GLOBAL_LIMITED' => '全局请求频率已达上限',
    'RATE_CONCURRENCY_LIMITED' => '并发请求数已达上限',

    // ─── 签名 ────────────────────────────────────────────────
    'SIG_MISSING' => '缺少签名',
    'SIG_INVALID' => '签名无效',
    'SIG_TIMESTAMP_INVALID' => '签名时间戳格式无效',
    'SIG_TIMESTAMP_EXPIRED' => '签名时间戳已过期',
    'SIG_NONCE_REUSED' => 'Nonce 已被使用',
    'SIG_HEADER_MISSING' => '缺少签名头',
    'SIG_BODY_MISMATCH' => '请求体签名不匹配',

    // ─── 非重复请求 ──────────────────────────────────────────
    'IDEMPOTENT_KEY_MISSING' => '缺少幂等键 (Idempotency-Key)',
    'IDEMPOTENT_KEY_REPLAYED' => '幂等键已被使用且请求结果不同',
    'IDEMPOTENT_IN_PROGRESS' => '该幂等键对应的请求正在处理中',

    // ─── 计费与订阅 ──────────────────────────────────────────
    'BILL_SUBSCRIPTION_EXPIRED' => '订阅已过期',
    'BILL_PAYMENT_FAILED' => '支付失败',
    'BILL_PAYMENT_DECLINED' => '支付被拒绝，请检查支付方式',
    'BILL_INSUFFICIENT_FUNDS' => '余额不足',
    'BILL_REFUND_FAILED' => '退款失败',
    'BILL_PLAN_NOT_FOUND' => '定价方案不存在',
    'BILL_PLAN_UNAVAILABLE' => '定价方案不可用',
    'BILL_COUPON_INVALID' => '优惠券无效',
    'BILL_COUPON_EXPIRED' => '优惠券已过期',
    'BILL_COUPON_USED' => '优惠券已被使用',
    'BILL_TRIAL_EXPIRED' => '试用期已结束',
    'BILL_TRIAL_NOT_AVAILABLE' => '试用不可用',
    'BILL_GRACE_ENDED' => '宽限期已结束',

    // ─── 发票 ────────────────────────────────────────────────
    'INV_NOT_FOUND' => '发票不存在',
    'INV_ALREADY_PAID' => '发票已支付',
    'INV_ALREADY_CANCELLED' => '发票已取消',
    'INV_OVERDUE' => '发票已逾期',
    'INV_REFUNDED' => '发票已退款',

    // ─── 税务 ────────────────────────────────────────────────
    'TAX_RATE_NOT_FOUND' => '税率未配置',
    'TAX_COUNTRY_NOT_SUPPORTED' => '不支持的税区国家',
    'TAX_EXEMPTION_INVALID' => '免税证明无效',
    'TAX_CALCULATION_FAILED' => '税额计算失败',

    // ─── 设备 ────────────────────────────────────────────────
    'DEVICE_NOT_FOUND' => '设备不存在',
    'DEVICE_LIMIT_EXCEEDED' => '设备数量已达上限',
    'DEVICE_FINGERPRINT_MISMATCH' => '设备指纹不匹配',
    'DEVICE_TRUST_EXPIRED' => '设备信任已过期',
    'DEVICE_REVOKED' => '设备已被吊销',
    'DEVICE_REGION_BLOCKED' => '设备所在区域被禁止',

    // ─── 自定义域名 ──────────────────────────────────────────
    'DOMAIN_EXISTS' => '该域名已被绑定',
    'DOMAIN_NOT_FOUND' => '域名不存在',
    'DOMAIN_VERIFICATION_FAILED' => '域名验证失败',
    'DOMAIN_SSL_ERROR' => '域名 SSL 证书配置错误',
    'DOMAIN_NOT_CONNECTED' => '域名未连接或解析不正确',

    // ─── Webhook ──────────────────────────────────────────────
    'WEBHOOK_ENDPOINT_INACTIVE' => 'Webhook 端点已停用',
    'WEBHOOK_ENDPOINT_NOT_FOUND' => 'Webhook 端点不存在',
    'WEBHOOK_DELIVERY_FAILED' => 'Webhook 投递失败',
    'WEBHOOK_SIGNATURE_MISMATCH' => 'Webhook 签名不匹配',
    'WEBHOOK_PAYLOAD_TOO_LARGE' => 'Webhook 负载过大',
    'WEBHOOK_RATE_LIMITED' => 'Webhook 发送频率过高',
    'WEBHOOK_NO_REPLAYABLE_EVENTS' => '没有可重放的事件',
    'WEBHOOK_CIRCUIT_OPEN' => 'Webhook 断路器已打开',

    // ─── LLM ──────────────────────────────────────────────────
    'LLM_ERROR' => 'AI 服务返回错误',
    'LLM_TIMEOUT' => 'AI 服务请求超时',
    'LLM_RATE_LIMITED' => 'AI 服务请求频率过高',
    'LLM_INVALID_RESPONSE' => 'AI 服务返回格式异常',
    'LLM_CONTENT_FILTERED' => 'AI 输出内容被过滤',
    'LLM_CONTEXT_OVERFLOW' => 'AI 上下文长度超限',
    'LLM_PROVIDER_UNAVAILABLE' => 'AI 服务商不可用',
    'LLM_FALLBACK_ALL_FAILED' => '所有 AI 服务商均不可用',
    'CONNECTION_FAILED' => '连接失败：:message',

    // ─── 验证 ─────────────────────────────────────────────────────
    'VALIDATION_ERROR' => '验证失败',
    'VALIDATION_INVALID_INPUT' => '输入参数无效',
    'VALIDATION_MISSING_FIELD' => '缺少必填字段：:field',
    'VALIDATION_INVALID_FORMAT' => '数据格式不正确：:field',
    'VALIDATION_BUSINESS_RULE' => '业务规则验证失败：:message',

    // ─── 资源不存在 ──────────────────────────────────────────
    'NOT_FOUND' => '资源不存在',
    'RESOURCE_DELETED' => '资源已被删除',

    // ─── 通用权限 ──────────────────────────────────────────
    'UNAUTHORIZED' => '未授权访问',
    'FORBIDDEN' => '权限不足',
    'FORBIDDEN_IP' => 'IP 不在白名单中',
    'FORBIDDEN_REGION' => '所在区域被禁止访问',
    'FORBIDDEN_MAINTENANCE' => '系统维护中，暂时禁止访问',
    'SYSTEM_ROLE' => '系统角色不可删除',
    'PERMISSION_DENIED' => '权限被拒绝',

    // ─── 节流与断路器 ──────────────────────────────────────
    'CIRCUIT_OPEN' => '断路器已打开，请求被拒绝',
    'CIRCUIT_HALF_OPEN' => '断路器处于半开状态',
    'BODY_TOO_LARGE' => '请求体过大',
    'PAYLOAD_TOO_LARGE' => '数据负载过大',

    // ─── 系统内部 ──────────────────────────────────────────
    'SYS_INTERNAL_ERROR' => '系统内部错误',
    'SYS_MAINTENANCE' => '系统正在维护中，请稍后再试',
    'SYS_DEPENDENCY_FAILURE' => '依赖服务异常',
    'SYS_DATABASE_ERROR' => '数据库异常',
    'SYS_CACHE_ERROR' => '缓存服务异常',
    'SYS_QUEUE_ERROR' => '队列服务异常',
    'SYS_STORAGE_ERROR' => '存储服务异常',
    'SYS_CONFIG_ERROR' => '系统配置错误',
    'SYS_SERVICE_UNAVAILABLE' => '服务暂不可用',
    'SYS_UPSTREAM_TIMEOUT' => '上游服务超时',
    'SYS_THIRD_PARTY_ERROR' => '第三方服务异常：:message',

    // ─── 租户 ──────────────────────────────────────────────
    'TENANT_NOT_FOUND' => '租户不存在',
    'TENANT_DISABLED' => '租户已被禁用',
    'TENANT_QUOTA_EXCEEDED' => '租户配额已超限',

    // ─── Feature Flag ──────────────────────────────────────────
    'FF_NOT_FOUND' => 'Feature Flag 不存在',
    'FF_EVALUATION_ERROR' => 'Feature Flag 评估异常',
    'FF_PROVIDER_ERROR' => 'Feature Flag 提供商异常',

    // ─── 客户 ──────────────────────────────────────────────
    'CUSTOMER_NOT_FOUND' => '客户不存在',
    'CUSTOMER_DISABLED' => '客户已被禁用',
    'CUSTOMER_QUOTA_EXCEEDED' => '客户配额已超限',
    'CUSTOMER_HEALTH_LOW' => '客户健康度评分过低',

    // ─── 标签 ──────────────────────────────────────────────
    'TAG_NOT_FOUND' => '标签不存在',
    'TAG_NAME_DUPLICATE' => '标签名称已存在',

    // ─── 文件 ──────────────────────────────────────────────
    'FILE_TOO_LARGE' => '文件大小超过限制',
    'FILE_TYPE_NOT_ALLOWED' => '不支持的文件类型',
    'FILE_UPLOAD_FAILED' => '文件上传失败',
    'FILE_NOT_FOUND' => '文件不存在',

    // ─── 账户操作 ──────────────────────────────────────────────
    'REQUEST_PROCESSED' => '此申请已被处理',
    'COOLING_NOT_OVER' => '冷静期尚未结束，无法执行操作',
    'EXECUTION_FAILED' => '操作执行失败',

    // ─── API 版本 ──────────────────────────────────────────
    'API_VERSION_UNAVAILABLE' => 'API 版本不可用: :message',
    'API_VERSION_DEPRECATED' => 'API 版本已废弃，请参考文档迁移到新版本',
    'API_VERSION_RETIRED' => 'API 版本已退役',
    'VERSION_RETIRED' => '已退役的版本无法标记为废弃',
    'VERSION_NOT_DEPRECATED' => '只有已废弃的版本可以停用',

    // ─── 错误码系统自身 ──────────────────────────────────
    'ERRCODE_NOT_FOUND' => '错误码 :code 不存在',

    // ─── 未知 ──────────────────────────────────────────────────
    'UNKNOWN_ERROR' => '未知错误',

    // ─── 测试专用 ──────────────────────────────────────────
    'TEST_ERROR' => '测试错误',
];

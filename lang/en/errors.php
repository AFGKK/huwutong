<?php

/**
 * M2-34: Standardized Error Code Messages (English)
 *
 * Each message supports :placeholder substitution.
 */

return [

    // ─── Authentication & Authorization ──────────────────────────
    'AUTH_FAILED' => 'Authentication failed: invalid account or password',
    'INVALID_TOKEN' => 'Invalid token, please re-authenticate',
    'TOKEN_EXPIRED' => 'Token has expired, please re-authenticate',
    'ACCOUNT_DISABLED' => 'Account has been disabled',
    'ACCOUNT_PENDING_DELETION' => 'Account is pending deletion',
    'ACCOUNT_DELETED' => 'Account has been deleted',
    'INVITE_REQUIRED' => 'A valid invitation code is required to register',
    'INVALID_PASSWORD' => 'Current password is incorrect',
    'PASSWORD_REUSED' => 'Cannot use a recently used password',
    'TOO_FREQUENT' => 'Too many requests, please try again later',
    'CANNOT_REVOKE_CURRENT' => 'Cannot revoke current session',
    'ALREADY_VERIFIED' => 'Already verified, no action needed',
    'ALREADY_CONSENTED' => 'You have already consented to this agreement',
    'PENDING_REQUEST' => 'A pending request exists: :message',
    'ALREADY_BOUND' => 'This third-party account is already bound to another user',
    'UNBIND_FAILED' => 'Unbind failed: :message',
    'INVALID_CODE' => 'Verification code is invalid or expired',
    'INVITE_EXPIRED' => 'Invitation code has expired',
    'INVITE_USED' => 'Invitation code has already been used',
    'LOGIN_EXPIRED' => 'Login session has expired, please re-authenticate',

    // ─── SDK General ──────────────────────────────────────────
    'SDK_VERSION_DEPRECATED' => 'Current SDK version is deprecated, please upgrade',
    'SDK_UNSUPPORTED' => 'Current SDK version is no longer supported, please upgrade',
    'SDK_HEARTBEAT_INTERVAL' => 'Heartbeat interval too frequent, please follow the recommended interval',

    // ─── License ────────────────────────────────────────────
    'LICENSE_NOT_FOUND' => 'License key not found',
    'LICENSE_EXPIRED' => 'License has expired',
    'LICENSE_NOT_ACTIVE' => 'License is not active or has been deactivated',
    'LICENSE_SUSPENDED' => 'License has been suspended',
    'LICENSE_REVOKED' => 'License has been revoked',
    'LICENSE_PENDING_APPROVAL' => 'License change pending approval',
    'LICENSE_ACTIVATION_LIMIT' => 'License has reached the maximum activation limit',
    'LICENSE_DEVICE_LIMIT' => 'License has reached the maximum device limit',
    'LICENSE_FINGERPRINT_MISMATCH' => 'Device fingerprint mismatch',
    'LICENSE_FILE_INVALID' => 'Invalid license file format',
    'LICENSE_FILE_TAMPERED' => 'License file has been tampered with',
    'LICENSE_GRACE_PERIOD' => 'License has expired, currently in grace period (:days days remaining)',
    'LICENSE_IN_MAINTENANCE' => 'License system is under maintenance',
    'LICENSE_ALREADY_ACTIVATED' => 'License already activated',

    // ─── Activation / Offline ────────────────────────────────
    'ACT_SIGNATURE_INVALID' => 'Activation signature is invalid',
    'ACT_CERTIFICATE_EXPIRED' => 'Offline activation certificate has expired',
    'ACT_NO_CERTIFICATE' => 'No offline signing certificate available',
    'ACT_OFFLINE_EXPIRED' => 'Offline activation code has expired',

    // ─── API Key ──────────────────────────────────────────
    'API_KEY_REQUIRED' => 'API Key is required',
    'API_KEY_INVALID' => 'Invalid API Key',
    'API_KEY_EXPIRED' => 'API Key has expired',
    'API_KEY_IP_MISMATCH' => 'API Key does not allow the current IP address',
    'API_KEY_INSUFFICIENT' => 'Insufficient API Key permissions',
    'API_KEY_METHOD_DENIED' => 'API Key does not allow this HTTP method',
    'API_KEY_ENDPOINT_DENIED' => 'API Key does not allow this endpoint',
    'API_KEY_QUOTA_EXCEEDED' => 'API Key request quota has been exhausted',
    'API_KEY_REVOKED' => 'API Key has been revoked',
    'API_KEY_SUSPENDED' => 'API Key has been suspended',
    'MAX_KEYS_REACHED' => 'Maximum of :max API keys reached',

    // ─── MFA ──────────────────────────────────────────────
    'MFA_CODE_INVALID' => 'Invalid MFA code',
    'MFA_NOT_ENABLED' => 'MFA is not enabled',
    'MFA_ALREADY_ENABLED' => 'MFA is already enabled',
    'MFA_BACKUP_USED' => 'MFA backup code has been used',
    'MFA_REQUIRED' => 'MFA verification required',

    // ─── SSO ──────────────────────────────────────────────
    'SSO_PROVIDER_INACTIVE' => 'SSO provider is not active',
    'SSO_PROVIDER_NOT_FOUND' => 'SSO provider not found',
    'SSO_ASSERTION_INVALID' => 'Invalid SSO assertion',

    // ─── Rate Limiting ──────────────────────────────────────
    'RATE_LIMITED' => 'Request rate exceeded, please try again later',
    'RATE_BURST_LIMITED' => 'Burst request rate exceeded, please slow down',
    'RATE_GLOBAL_LIMITED' => 'Global request rate limit reached',
    'RATE_CONCURRENCY_LIMITED' => 'Concurrent request limit reached',

    // ─── Signature ────────────────────────────────────────
    'SIG_MISSING' => 'Signature is missing',
    'SIG_INVALID' => 'Invalid signature',
    'SIG_TIMESTAMP_INVALID' => 'Invalid signature timestamp format',
    'SIG_TIMESTAMP_EXPIRED' => 'Signature timestamp has expired',
    'SIG_NONCE_REUSED' => 'Nonce has already been used',
    'SIG_HEADER_MISSING' => 'Missing signature header',
    'SIG_BODY_MISMATCH' => 'Request body signature mismatch',

    // ─── Idempotency ──────────────────────────────────────
    'IDEMPOTENT_KEY_MISSING' => 'Idempotency-Key header is required',
    'IDEMPOTENT_KEY_REPLAYED' => 'Idempotency key already used with a different request',
    'IDEMPOTENT_IN_PROGRESS' => 'Request with this idempotency key is in progress',

    // ─── Billing & Subscription ──────────────────────────
    'BILL_SUBSCRIPTION_EXPIRED' => 'Subscription has expired',
    'BILL_PAYMENT_FAILED' => 'Payment failed',
    'BILL_PAYMENT_DECLINED' => 'Payment declined, please check your payment method',
    'BILL_INSUFFICIENT_FUNDS' => 'Insufficient funds',
    'BILL_REFUND_FAILED' => 'Refund failed',
    'BILL_PLAN_NOT_FOUND' => 'Pricing plan not found',
    'BILL_PLAN_UNAVAILABLE' => 'Pricing plan is not available',
    'BILL_COUPON_INVALID' => 'Coupon is invalid',
    'BILL_COUPON_EXPIRED' => 'Coupon has expired',
    'BILL_COUPON_USED' => 'Coupon has already been used',
    'BILL_TRIAL_EXPIRED' => 'Trial period has ended',
    'BILL_TRIAL_NOT_AVAILABLE' => 'Trial is not available',
    'BILL_GRACE_ENDED' => 'Grace period has ended',

    // ─── Invoice ──────────────────────────────────────────
    'INV_NOT_FOUND' => 'Invoice not found',
    'INV_ALREADY_PAID' => 'Invoice has already been paid',
    'INV_ALREADY_CANCELLED' => 'Invoice has already been cancelled',
    'INV_OVERDUE' => 'Invoice is overdue',
    'INV_REFUNDED' => 'Invoice has been refunded',

    // ─── Tax ──────────────────────────────────────────────
    'TAX_RATE_NOT_FOUND' => 'Tax rate not configured',
    'TAX_COUNTRY_NOT_SUPPORTED' => 'Tax region/country is not supported',
    'TAX_EXEMPTION_INVALID' => 'Tax exemption certificate is invalid',
    'TAX_CALCULATION_FAILED' => 'Tax calculation failed',

    // ─── Device ──────────────────────────────────────────
    'DEVICE_NOT_FOUND' => 'Device not found',
    'DEVICE_LIMIT_EXCEEDED' => 'Device limit exceeded',
    'DEVICE_FINGERPRINT_MISMATCH' => 'Device fingerprint mismatch',
    'DEVICE_TRUST_EXPIRED' => 'Device trust has expired',
    'DEVICE_REVOKED' => 'Device has been revoked',
    'DEVICE_REGION_BLOCKED' => 'Device region is blocked',

    // ─── Custom Domain ──────────────────────────────────
    'DOMAIN_EXISTS' => 'Domain is already bound',
    'DOMAIN_NOT_FOUND' => 'Domain not found',
    'DOMAIN_VERIFICATION_FAILED' => 'Domain verification failed',
    'DOMAIN_SSL_ERROR' => 'Domain SSL certificate configuration error',
    'DOMAIN_NOT_CONNECTED' => 'Domain is not connected or DNS resolution is incorrect',

    // ─── Webhook ──────────────────────────────────────────
    'WEBHOOK_ENDPOINT_INACTIVE' => 'Webhook endpoint is inactive',
    'WEBHOOK_ENDPOINT_NOT_FOUND' => 'Webhook endpoint not found',
    'WEBHOOK_DELIVERY_FAILED' => 'Webhook delivery failed',
    'WEBHOOK_SIGNATURE_MISMATCH' => 'Webhook signature mismatch',
    'WEBHOOK_PAYLOAD_TOO_LARGE' => 'Webhook payload is too large',
    'WEBHOOK_RATE_LIMITED' => 'Webhook send rate too high',
    'WEBHOOK_NO_REPLAYABLE_EVENTS' => 'No replayable events available',
    'WEBHOOK_CIRCUIT_OPEN' => 'Webhook circuit breaker is open',

    // ─── LLM ──────────────────────────────────────────────
    'LLM_ERROR' => 'AI service returned an error',
    'LLM_TIMEOUT' => 'AI service request timed out',
    'LLM_RATE_LIMITED' => 'AI service request rate too high',
    'LLM_INVALID_RESPONSE' => 'AI service returned an invalid response format',
    'LLM_CONTENT_FILTERED' => 'AI output content was filtered',
    'LLM_CONTEXT_OVERFLOW' => 'AI context length exceeded',
    'LLM_PROVIDER_UNAVAILABLE' => 'AI provider is unavailable',
    'LLM_FALLBACK_ALL_FAILED' => 'All AI providers are unavailable',
    'CONNECTION_FAILED' => 'Connection failed: :message',

    // ─── Validation ─────────────────────────────────────────
    'VALIDATION_ERROR' => 'Validation failed',
    'VALIDATION_INVALID_INPUT' => 'Invalid input parameter',
    'VALIDATION_MISSING_FIELD' => 'Missing required field: :field',
    'VALIDATION_INVALID_FORMAT' => 'Invalid data format: :field',
    'VALIDATION_BUSINESS_RULE' => 'Business rule validation failed: :message',

    // ─── Resource Not Found ──────────────────────────────
    'NOT_FOUND' => 'Resource not found',
    'RESOURCE_DELETED' => 'Resource has been deleted',

    // ─── General Permissions ──────────────────────────────
    'UNAUTHORIZED' => 'Unauthorized access',
    'FORBIDDEN' => 'Insufficient permissions',
    'FORBIDDEN_IP' => 'IP address is not whitelisted',
    'FORBIDDEN_REGION' => 'Access from your region is forbidden',
    'FORBIDDEN_MAINTENANCE' => 'System is under maintenance, access temporarily forbidden',
    'SYSTEM_ROLE' => 'System roles cannot be deleted',
    'PERMISSION_DENIED' => 'Permission denied',

    // ─── Throttling & Circuit Breaker ──────────────────────
    'CIRCUIT_OPEN' => 'Circuit breaker is open, request rejected',
    'CIRCUIT_HALF_OPEN' => 'Circuit breaker is in half-open state',
    'BODY_TOO_LARGE' => 'Request body is too large',
    'PAYLOAD_TOO_LARGE' => 'Payload is too large',

    // ─── System Internal ──────────────────────────────────
    'SYS_INTERNAL_ERROR' => 'Internal system error',
    'SYS_MAINTENANCE' => 'System is under maintenance, please try again later',
    'SYS_DEPENDENCY_FAILURE' => 'Dependency service error',
    'SYS_DATABASE_ERROR' => 'Database error',
    'SYS_CACHE_ERROR' => 'Cache service error',
    'SYS_QUEUE_ERROR' => 'Queue service error',
    'SYS_STORAGE_ERROR' => 'Storage service error',
    'SYS_CONFIG_ERROR' => 'System configuration error',
    'SYS_SERVICE_UNAVAILABLE' => 'Service is temporarily unavailable',
    'SYS_UPSTREAM_TIMEOUT' => 'Upstream service timed out',
    'SYS_THIRD_PARTY_ERROR' => 'Third-party service error: :message',

    // ─── Tenant ──────────────────────────────────────────
    'TENANT_NOT_FOUND' => 'Tenant not found',
    'TENANT_DISABLED' => 'Tenant has been disabled',
    'TENANT_QUOTA_EXCEEDED' => 'Tenant quota exceeded',

    // ─── Feature Flag ──────────────────────────────────────
    'FF_NOT_FOUND' => 'Feature flag not found',
    'FF_EVALUATION_ERROR' => 'Feature flag evaluation error',
    'FF_PROVIDER_ERROR' => 'Feature flag provider error',

    // ─── Customer ──────────────────────────────────────────
    'CUSTOMER_NOT_FOUND' => 'Customer not found',
    'CUSTOMER_DISABLED' => 'Customer has been disabled',
    'CUSTOMER_QUOTA_EXCEEDED' => 'Customer quota exceeded',
    'CUSTOMER_HEALTH_LOW' => 'Customer health score is too low',

    // ─── Tag ──────────────────────────────────────────────
    'TAG_NOT_FOUND' => 'Tag not found',
    'TAG_NAME_DUPLICATE' => 'Tag name already exists',

    // ─── File ──────────────────────────────────────────────
    'FILE_TOO_LARGE' => 'File size exceeds the limit',
    'FILE_TYPE_NOT_ALLOWED' => 'File type is not allowed',
    'FILE_UPLOAD_FAILED' => 'File upload failed',
    'FILE_NOT_FOUND' => 'File not found',

    // ─── Account Deletion ──────────────────────────────────
    'REQUEST_PROCESSED' => 'This request has already been processed',
    'COOLING_NOT_OVER' => 'Cooling-off period has not ended',
    'EXECUTION_FAILED' => 'Operation execution failed',

    // ─── API Version ──────────────────────────────────────
    'API_VERSION_UNAVAILABLE' => 'API version unavailable: :message',
    'API_VERSION_DEPRECATED' => 'API version is deprecated, please migrate to a newer version',
    'API_VERSION_RETIRED' => 'API version has been retired',
    'VERSION_RETIRED' => 'Retired versions cannot be marked as deprecated',
    'VERSION_NOT_DEPRECATED' => 'Only deprecated versions can be deactivated',

    // ─── Error Code System ──────────────────────────────
    'ERRCODE_NOT_FOUND' => 'Error code :code not found',

    // ─── Unknown ──────────────────────────────────────────
    'UNKNOWN_ERROR' => 'An unknown error occurred',

    // ─── Test ──────────────────────────────────────────
    'TEST_ERROR' => 'Test error',
];

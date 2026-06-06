<?php

namespace App\Services;

use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 统一审计日志服务
 *
 * 全生命周期记录所有关键操作，支持：
 * - License 状态变更审计
 * - 设备激活/解绑审计
 * - 用户操作审计
 * - 安全事件审计
 * - 带关联对象的审计查询
 */
class AuditService
{
    /**
     * 记录审计日志
     */
    public function log(
        string $action,
        string $description,
        ?int   $tenantId = null,
        ?int   $userId = null,
        ?int   $licenseId = null,
        ?int   $customerId = null,
        ?int   $deviceId = null,
        ?int   $productId = null,
        string $type = 'audit',
        ?array $payload = null,
    ): Log {
        $request = request();

        return Log::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId ?? Auth::id(),
            'license_id' => $licenseId,
            'customer_id' => $customerId,
            'device_id' => $deviceId,
            'product_id' => $productId,
            'type' => $type,
            'action' => $action,
            'description' => $description,
            'payload' => $payload,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * License 状态变更审计
     */
    public function licenseStatusChanged(
        int     $tenantId,
        int     $licenseId,
        string  $licenseKey,
        string  $oldStatus,
        string  $newStatus,
        ?string $reason = null,
        ?int    $userId = null,
    ): Log {
        return $this->log(
            action: 'license.status_changed',
            description: sprintf(
                'License [%s] 状态变更: %s → %s',
                $licenseKey,
                $oldStatus,
                $newStatus,
            ),
            tenantId: $tenantId,
            licenseId: $licenseId,
            userId: $userId,
            payload: [
                'license_key' => $licenseKey,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
            ],
        );
    }

    /**
     * License 创建审计
     */
    public function licenseCreated(
        int    $tenantId,
        int    $licenseId,
        string $licenseKey,
        string $licenseType,
        ?int   $userId = null,
    ): Log {
        return $this->log(
            action: 'license.created',
            description: sprintf('创建 License [%s] (%s)', $licenseKey, $licenseType),
            tenantId: $tenantId,
            licenseId: $licenseId,
            userId: $userId,
            payload: [
                'license_key' => $licenseKey,
                'type' => $licenseType,
            ],
        );
    }

    /**
     * 设备激活审计
     */
    public function deviceActivated(
        int    $tenantId,
        int    $licenseId,
        string $licenseKey,
        int    $deviceId,
        string $fingerprint,
        ?int   $userId = null,
    ): Log {
        return $this->log(
            action: 'device.activated',
            description: sprintf('设备 [%s] 激活于 License [%s]', substr($fingerprint, 0, 16) . '...', $licenseKey),
            tenantId: $tenantId,
            licenseId: $licenseId,
            deviceId: $deviceId,
            userId: $userId,
            payload: [
                'license_key' => $licenseKey,
                'fingerprint' => $fingerprint,
            ],
        );
    }

    /**
     * 设备解绑审计
     */
    public function deviceDeactivated(
        int    $tenantId,
        int    $licenseId,
        string $licenseKey,
        int    $deviceId,
        string $fingerprint,
        ?int   $userId = null,
    ): Log {
        return $this->log(
            action: 'device.deactivated',
            description: sprintf('设备 [%s] 从 License [%s] 解绑', substr($fingerprint, 0, 16) . '...', $licenseKey),
            tenantId: $tenantId,
            licenseId: $licenseId,
            deviceId: $deviceId,
            userId: $userId,
            payload: [
                'license_key' => $licenseKey,
                'fingerprint' => $fingerprint,
            ],
        );
    }

    /**
     * 用户操作审计（增删改查等）
     */
    public function userAction(
        string $action,
        string $description,
        ?int   $tenantId = null,
        ?int   $userId = null,
        ?array $payload = null,
    ): Log {
        return $this->log(
            action: $action,
            description: $description,
            tenantId: $tenantId,
            userId: $userId,
            payload: $payload,
        );
    }

    /**
     * 安全事件审计（登录失败、权限越界等）
     */
    public function securityEvent(
        string $action,
        string $description,
        ?int   $tenantId = null,
        ?int   $userId = null,
        ?array $payload = null,
    ): Log {
        return $this->log(
            action: $action,
            description: $description,
            tenantId: $tenantId,
            userId: $userId,
            type: 'security',
            payload: $payload,
        );
    }

    /**
     * 错误事件审计
     */
    public function error(
        string $action,
        string $description,
        ?int   $tenantId = null,
        ?array $payload = null,
    ): Log {
        return $this->log(
            action: $action,
            description: $description,
            tenantId: $tenantId,
            type: 'error',
            payload: $payload,
        );
    }
}

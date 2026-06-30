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

    /**
     * 通用模型变更审计
     *
     * 记录 Eloquent 模型中指定字段的变更历史。
     *
     * @param Model|null $model    变更的模型实例
     * @param array      $original 变更前的原始值
     * @param array      $changes  变更后的新值
     * @param array      $context  附加上下文（tenant_id, user_id 等）
     */
    public function modelChanged(
        ?Model $model,
        array  $original,
        array  $changes,
        array  $context = [],
    ): ?Log {
        // 只记录有实际变更的字段
        $diffs = [];
        foreach ($changes as $field => $newValue) {
            if (array_key_exists($field, $original) && $original[$field] !== $newValue) {
                $diffs[$field] = [
                    'old' => $original[$field],
                    'new' => $newValue,
                ];
            }
        }

        if (empty($diffs)) {
            return null;
        }

        $modelName = $model ? class_basename($model) : 'Unknown';
        $modelId = $model?->getKey();
        $changedFields = implode(', ', array_keys($diffs));

        return $this->log(
            action: $context['action'] ?? strtolower($modelName) . '.updated',
            description: sprintf('%s [%s] 字段变更: %s', $modelName, $modelId, $changedFields),
            tenantId: $context['tenant_id'] ?? null,
            userId: $context['user_id'] ?? null,
            licenseId: $context['license_id'] ?? null,
            customerId: $context['customer_id'] ?? null,
            deviceId: $context['device_id'] ?? null,
            productId: $context['product_id'] ?? null,
            type: $context['type'] ?? 'audit',
            payload: [
                'model' => $modelName,
                'model_id' => $modelId,
                'diffs' => $diffs,
                'original' => $original,
            ],
        );
    }
}

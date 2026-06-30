<?php

namespace App\Services\Grpc;

/**
 * License gRPC 服务客户端
 *
 * 提供 License 激活、验证、吊销等 gRPC 调用封装。
 * 支持 grpc/http2/rest 三种模式。
 */
class LicenseGrpcService extends GrpcService
{
    protected string $serviceName = 'license';

    protected function getConfigKey(): string
    {
        return 'license_service';
    }

    /**
     * 激活 License
     */
    public function activate(string $licenseKey, string $deviceFingerprint, array $metadata = []): array
    {
        return $this->call(__FUNCTION__, [
            'license_key' => $licenseKey,
            'device_fingerprint' => $deviceFingerprint,
            'metadata' => $metadata,
        ]);
    }

    /**
     * 验证 License
     */
    public function validate(string $licenseKey, string $deviceFingerprint): array
    {
        return $this->call(__FUNCTION__, [
            'license_key' => $licenseKey,
            'device_fingerprint' => $deviceFingerprint,
        ]);
    }

    /**
     * 吊销 License
     */
    public function revoke(string $licenseKey, string $reason): array
    {
        return $this->call(__FUNCTION__, [
            'license_key' => $licenseKey,
            'reason' => $reason,
        ]);
    }

    /**
     * 获取 License 详情
     */
    public function getLicense(string $licenseKey): array
    {
        return $this->call(__FUNCTION__, [
            'license_key' => $licenseKey,
        ]);
    }

    /**
     * 列表 License
     */
    public function listLicenses(array $filters = []): array
    {
        return $this->call(__FUNCTION__, $filters);
    }

    /**
     * 挂起 License
     */
    public function suspend(string $licenseKey, string $reason): array
    {
        return $this->call(__FUNCTION__, [
            'license_key' => $licenseKey,
            'reason' => $reason,
        ]);
    }

    /**
     * 恢复 License
     */
    public function unsuspend(string $licenseKey): array
    {
        return $this->call(__FUNCTION__, [
            'license_key' => $licenseKey,
        ]);
    }

    /**
     * 更新 License 状态
     */
    public function updateStatus(string $licenseKey, string $newStatus, string $reason): array
    {
        return $this->call(__FUNCTION__, [
            'license_key' => $licenseKey,
            'new_status' => $newStatus,
            'reason' => $reason,
        ]);
    }

    /**
     * 检查 Feature 可用性
     */
    public function checkFeature(string $licenseKey, string $featureKey): array
    {
        return $this->call(__FUNCTION__, [
            'license_key' => $licenseKey,
            'feature_key' => $featureKey,
        ]);
    }
}

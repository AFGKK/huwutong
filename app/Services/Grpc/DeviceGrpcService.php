<?php

namespace App\Services\Grpc;

/**
 * Device gRPC 服务客户端
 */
class DeviceGrpcService extends GrpcService
{
    protected string $serviceName = 'device';

    protected function getConfigKey(): string
    {
        return 'device_service';
    }

    public function registerDevice(string $licenseKey, string $fingerprint, array $info = []): array
    {
        return $this->call(__FUNCTION__, array_merge([
            'license_key' => $licenseKey,
            'fingerprint' => $fingerprint,
        ], $info));
    }

    public function getDevice(int $deviceId): array
    {
        return $this->call(__FUNCTION__, ['device_id' => $deviceId]);
    }

    public function listDevices(int $licenseId, array $filters = []): array
    {
        return $this->call(__FUNCTION__, array_merge(['license_id' => $licenseId], $filters));
    }

    public function updateTrustScore(int $deviceId, int $score, string $reason): array
    {
        return $this->call(__FUNCTION__, [
            'device_id' => $deviceId,
            'trust_score' => $score,
            'reason' => $reason,
        ]);
    }

    public function removeDevice(int $deviceId, string $reason): array
    {
        return $this->call(__FUNCTION__, [
            'device_id' => $deviceId,
            'reason' => $reason,
        ]);
    }

    public function blacklistDevice(int $deviceId, string $reason): array
    {
        return $this->call(__FUNCTION__, [
            'device_id' => $deviceId,
            'reason' => $reason,
        ]);
    }

    public function matchFingerprint(int $licenseId, string $fingerprint): array
    {
        return $this->call(__FUNCTION__, [
            'license_id' => $licenseId,
            'fingerprint' => $fingerprint,
        ]);
    }
}

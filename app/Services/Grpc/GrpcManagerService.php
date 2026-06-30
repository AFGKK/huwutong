<?php

namespace App\Services\Grpc;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * gRPC 服务管理器 (M1.3-28)
 *
 * 统一管理所有 gRPC 服务客户端、服务端、服务发现、健康检查。
 */
class GrpcManagerService
{
    protected LicenseGrpcService $license;
    protected DeviceGrpcService $device;
    protected BillingGrpcService $billing;
    protected NotificationGrpcService $notification;

    protected array $services = [];

    public function __construct()
    {
        $this->license = app(LicenseGrpcService::class);
        $this->device = app(DeviceGrpcService::class);
        $this->billing = app(BillingGrpcService::class);
        $this->notification = app(NotificationGrpcService::class);

        $this->services = [
            'license' => $this->license,
            'device' => $this->device,
            'billing' => $this->billing,
            'notification' => $this->notification,
        ];
    }

    /**
     * 获取 License 服务
     */
    public function license(): LicenseGrpcService
    {
        return $this->license;
    }

    /**
     * 获取 Device 服务
     */
    public function device(): DeviceGrpcService
    {
        return $this->device;
    }

    /**
     * 获取 Billing 服务
     */
    public function billing(): BillingGrpcService
    {
        return $this->billing;
    }

    /**
     * 获取 Notification 服务
     */
    public function notification(): NotificationGrpcService
    {
        return $this->notification;
    }

    /**
     * 仪表盘：获取所有服务状态
     */
    public function getDashboard(): array
    {
        $mode = config('grpc.mode', 'rest');
        $enabled = config('grpc.enabled', false);

        $serviceStatuses = [];
        foreach ($this->services as $name => $service) {
            try {
                $healthy = $service->isHealthy();
                $cb = $service->getCircuitBreakerStatus();
            } catch (Exception $e) {
                $healthy = false;
                $cb = ['circuit_open' => false, 'failure_count' => 0];
            }

            $serviceStatuses[$name] = [
                'name' => $name,
                'healthy' => $healthy,
                'circuit_breaker' => $cb,
            ];
        }

        return [
            'enabled' => $enabled,
            'mode' => $mode,
            'services' => $serviceStatuses,
            'healthy_count' => count(array_filter($serviceStatuses, fn($s) => $s['healthy'])),
            'total_count' => count($serviceStatuses),
        ];
    }

    /**
     * 健康检查
     */
    public function healthCheck(): array
    {
        $results = [];

        foreach ($this->services as $name => $service) {
            try {
                $start = microtime(true);
                $healthy = $service->isHealthy();
                $latency = (microtime(true) - $start) * 1000;
            } catch (Exception $e) {
                $healthy = false;
                $latency = -1;
            }

            $results[$name] = [
                'healthy' => $healthy,
                'latency_ms' => round($latency, 2),
            ];
        }

        return [
            'results' => $results,
            'all_healthy' => collect($results)->every(fn($r) => $r['healthy']),
            'mode' => config('grpc.mode', 'rest'),
        ];
    }

    /**
     * 重置所有服务熔断器
     */
    public function resetAllCircuitBreakers(): void
    {
        foreach ($this->services as $service) {
            $service->resetCircuitBreaker();
        }
    }

    /**
     * 获取所有熔断器状态
     */
    public function getAllCircuitBreakerStatus(): array
    {
        $result = [];
        foreach ($this->services as $name => $service) {
            $result[$name] = $service->getCircuitBreakerStatus();
        }
        return $result;
    }

    /**
     * 获取 gRPC 配置状态
     */
    public function getConfig(): array
    {
        return [
            'enabled' => config('grpc.enabled', false),
            'mode' => config('grpc.mode', 'rest'),
            'server_port' => config('grpc.server.port', 50051),
            'client_timeout' => config('grpc.client.timeout', 10),
            'client_retries' => config('grpc.client.retries', 3),
            'discovery' => config('grpc.discovery.type', 'static'),
            'protos' => array_keys(config('grpc.protos.services', [])),
        ];
    }

    /**
     * 获取服务地址信息
     */
    public function getEndpoints(): array
    {
        $endpoints = [];

        foreach ($this->services as $name => $service) {
            $endpoints[$name] = [
                'host' => $service->host ?? config("grpc.client.{$name}_service.host", 'localhost'),
                'port' => $service->port ?? config("grpc.client.{$name}_service.port", 50051),
                'address' => ($service->host ?? 'localhost') . ':' . ($service->port ?? 50051),
            ];
        }

        return $endpoints;
    }
}

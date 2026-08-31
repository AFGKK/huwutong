<?php

namespace App\Services\Grpc;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

/**
 * gRPC 核心服务抽象层 (M1.3-28)
 *
 * 支持三种运行模式：
 *   grpc:  真实 gRPC 扩展（生产推荐）
 *   http2: HTTP/2 JSON 模拟（无需扩展）
 *   rest:  REST 回退（开发环境）
 *
 * 自动熔断、超时、重试机制。
 */
abstract class GrpcService
{
    /**
     * 服务名称
     */
    protected string $serviceName;

    /**
     * 服务地址
     */
    protected string $host;

    /**
     * 服务端口
     */
    protected int $port;

    /**
     * 超时时间（秒）
     */
    protected int $timeout;

    /**
     * 重试次数
     */
    protected int $retries;

    /**
     * 熔断器状态
     */
    protected bool $circuitOpen = false;

    /**
     * 连续失败计数
     */
    protected int $failureCount = 0;

    /**
     * 熔断阈值
     */
    protected int $circuitThreshold = 10;

    /**
     * 构造方法
     */
    public function __construct()
    {
        $config = config("grpc.client.{$this->getConfigKey()}", []);
        $this->host = $config['host'] ?? 'localhost';
        $this->port = $config['port'] ?? 50051;
        $this->timeout = $config['timeout'] ?? (config('grpc.client.timeout', 10));
        $this->retries = config('grpc.client.retries', 3);
    }

    /**
     * 获取配置键名
     */
    abstract protected function getConfigKey(): string;

    /**
     * 获取服务标识
     */
    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    /**
     * 健康检查
     */
    public function isHealthy(): bool
    {
        if ($this->circuitOpen) {
            return false;
        }

        try {
            $this->call('health', ['service' => $this->serviceName]);
            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * 调用 gRPC 方法
     */
    protected function call(string $method, array $payload = []): array
    {
        if ($this->circuitOpen) {
            throw new GrpcException("Circuit breaker open for {$this->serviceName}", 503);
        }

        $lastException = null;

        for ($attempt = 0; $attempt <= $this->retries; $attempt++) {
            try {
                $result = $this->doCall($method, $payload);
                $this->failureCount = 0;
                return $result;
            } catch (Exception $e) {
                $lastException = $e;
                $this->failureCount++;

                Log::warning("gRPC 调用失败: {$this->serviceName}.{$method}", [
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ]);

                // 检查熔断
                if ($this->failureCount >= $this->circuitThreshold) {
                    $this->circuitOpen = true;
                    Log::alert("gRPC 熔断器已打开: {$this->serviceName}");
                }

                // 重试前等待
                if ($attempt < $this->retries) {
                    $delayMs = config('grpc.client.retry_delay_ms', 100) * ($attempt + 1);
                    usleep($delayMs * 1000);
                }
            }
        }

        throw new GrpcException(
            "gRPC 调用失败（已重试 {$this->retries} 次）: {$this->serviceName}.{$method}: " . ($lastException?->getMessage()),
            $lastException?->getCode() ?? 500
        );
    }

    /**
     * 执行实际的 gRPC 调用
     */
    protected function doCall(string $method, array $payload): array
    {
        $mode = config('grpc.mode', 'rest');

        return match ($mode) {
            'grpc' => $this->callGrpc($method, $payload),
            'http2' => $this->callHttp2($method, $payload),
            default => $this->callRest($method, $payload),
        };
    }

    /**
     * 真实 gRPC 扩展调用
     */
    protected function callGrpc(string $method, array $payload): array
    {
        // 当 grpc PHP 扩展安装后，此处调用生成的 gRPC 客户端
        // $client = new \App\Services\Grpc\Proto\License\LicenseServiceClient(
        //     "{$this->host}:{$this->port}", ['credentials' => \Grpc\ChannelCredentials::createInsecure()]
        // );
        // list($response, $status) = $client->$method($request)->wait();
        // if ($status->code !== 0) { throw ... }
        // return $this->protoToArray($response);

        throw new GrpcException(__("app.grpc.msg_4d65394e"));
    }

    /**
     * HTTP/2 JSON 模拟调用
     */
    protected function callHttp2(string $method, array $payload): array
    {
        $url = "http://{$this->host}:{$this->port}/grpc/{$this->serviceName}/{$method}";

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Content-Type' => 'application/grpc+json',
                'X-Grpc-Service' => $this->serviceName,
                'X-Grpc-Method' => $method,
            ])
            ->post($url, $payload);

        if (!$response->successful()) {
            throw new GrpcException(
                "HTTP/2 gRPC 调用失败: {$response->status()}",
                $response->status()
            );
        }

        $data = $response->json();
        if (isset($data['error'])) {
            throw new GrpcException($data['error']['message'] ?? 'gRPC error', $data['error']['code'] ?? 500);
        }

        return $data['result'] ?? $data;
    }

    /**
     * REST 回退调用（通过 REST API 转发）
     */
    protected function callRest(string $method, array $payload): array
    {
        $serviceRoutes = [
            'license' => '/api/v1/grpc/license',
            'device' => '/api/v1/grpc/device',
            'billing' => '/api/v1/grpc/billing',
            'notification' => '/api/v1/grpc/notification',
        ];

        $route = $serviceRoutes[$this->getConfigKey()] ?? '/api/v1/grpc';
        $url = rtrim(config('app.url', 'http://localhost:8000'), '/') . $route;

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-Grpc-Method' => $method,
                'X-Internal-Call' => 'true',
            ])
            ->post($url, $payload);

        if (!$response->successful()) {
            throw new GrpcException(
                "REST gRPC 回退失败: {$response->status()}",
                $response->status()
            );
        }

        return $response->json();
    }

    /**
     * 重置熔断器
     */
    public function resetCircuitBreaker(): void
    {
        $this->circuitOpen = false;
        $this->failureCount = 0;
        Log::info("gRPC 熔断器已重置: {$this->serviceName}");
    }

    /**
     * 获取熔断器状态
     */
    public function getCircuitBreakerStatus(): array
    {
        return [
            'service' => $this->serviceName,
            'circuit_open' => $this->circuitOpen,
            'failure_count' => $this->failureCount,
            'threshold' => $this->circuitThreshold,
        ];
    }
}

/**
 * gRPC 异常类
 */
class GrpcException extends Exception
{
    public function __construct(string $message = '', int $code = 500, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getGrpcStatus(): string
    {
        return match ($this->code) {
            0 => 'OK',
            1 => 'CANCELLED',
            2 => 'UNKNOWN',
            3 => 'INVALID_ARGUMENT',
            4 => 'DEADLINE_EXCEEDED',
            5 => 'NOT_FOUND',
            6 => 'ALREADY_EXISTS',
            7 => 'PERMISSION_DENIED',
            8 => 'RESOURCE_EXHAUSTED',
            9 => 'FAILED_PRECONDITION',
            10 => 'ABORTED',
            11 => 'OUT_OF_RANGE',
            12 => 'UNIMPLEMENTED',
            13 => 'INTERNAL',
            14 => 'UNAVAILABLE',
            15 => 'DATA_LOSS',
            16 => 'UNAUTHENTICATED',
            default => 'UNKNOWN',
        };
    }
}

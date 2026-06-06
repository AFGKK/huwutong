<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Trace\SpanExporter\SpanExporterFactory;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use Symfony\Component\HttpFoundation\Response;

/**
 * OpenTelemetry 集成服务
 *
 * 桥接 ApmService 与 OpenTelemetry 标准协议。
 * 可将请求追踪、DB 查询、外部调用等指标发送到 Jaeger/Grafana Tempo 等后端。
 *
 * 配置参考 .env：
 *   OTEL_SERVICE_NAME=huwutong-api
 *   OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318
 *   OTEL_TRACES_SAMPLER_RATIO=0.1
 */
class OpenTelemetryService
{
    protected ?TracerProvider $tracerProvider = null;
    protected bool $enabled = false;
    protected float $samplingRatio = 0.1;

    public function __construct()
    {
        $this->enabled = (bool) (env('OTEL_ENABLED', false));
        $this->samplingRatio = (float) (env('OTEL_TRACES_SAMPLER_RATIO', 0.1));

        if ($this->enabled) {
            $this->boot();
        }
    }

    /**
     * 初始化 OpenTelemetry TracerProvider
     */
    protected function boot(): void
    {
        try {
            $serviceName = env('OTEL_SERVICE_NAME', 'huwutong-api');
            $endpoint = env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://localhost:4318');

            $exporter = (new SpanExporterFactory())->create();

            $sampler = new ParentBased(
                new TraceIdRatioBasedSampler($this->samplingRatio)
            );

            $this->tracerProvider = new TracerProvider(
                exporter: $exporter,
                sampler: $sampler,
                spanProcessor: BatchSpanProcessor::create(
                    exporter: $exporter,
                    clock: ClockFactory::getDefault(),
                ),
            );

            Globals::setTracerProvider($this->tracerProvider);

            Log::info('OpenTelemetry 已初始化', [
                'service' => $serviceName,
                'endpoint' => $endpoint,
                'sampling_ratio' => $this->samplingRatio,
            ]);
        } catch (\Throwable $e) {
            Log::warning('OpenTelemetry 初始化失败，将以降级模式运行', [
                'error' => $e->getMessage(),
            ]);
            $this->enabled = false;
        }
    }

    /**
     * 是否为请求创建追踪跨度
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->tracerProvider !== null;
    }

    /**
     * 开始一个请求追踪跨度
     *
     * @return array{tracer: \OpenTelemetry\API\Trace\TracerInterface, span: \OpenTelemetry\API\Trace\SpanInterface}|null
     */
    public function startRequestSpan(Request $request): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $tracer = $this->tracerProvider->getTracer('huwutong', '1.0.0');

        $spanName = $request->method() . ' ' . $request->path();
        $span = $tracer->spanBuilder($spanName)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttributes(Attributes::create([
                'http.method' => $request->method(),
                'http.url' => $request->fullUrl(),
                'http.target' => $request->path(),
                'http.host' => $request->host(),
                'http.scheme' => $request->scheme(),
                'http.user_agent' => $request->userAgent() ?? '',
                'http.request_content_length' => $request->header('Content-Length', 0),
                'net.peer.ip' => $request->ip() ?? '',
                'net.host.port' => $request->getPort(),
            ]))
            ->startSpan();

        $context = $span->storeInContext(Context::getCurrent());
        Context::storage()->attach($context);

        return ['tracer' => $tracer, 'span' => $span];
    }

    /**
     * 结束请求追踪跨度
     */
    public function endRequestSpan(?array $traceContext, Response $response, float $durationMs): void
    {
        if (!$traceContext || !$this->isEnabled()) {
            return;
        }

        $span = $traceContext['span'];

        $span->setAttributes(Attributes::create([
            'http.status_code' => $response->getStatusCode(),
            'http.response_content_length' => $response->headers->get('Content-Length', 0),
            'duration_ms' => $durationMs,
        ]));

        if ($response->getStatusCode() >= 500) {
            $span->setStatus(StatusCode::STATUS_ERROR, 'HTTP ' . $response->getStatusCode());
        } elseif ($response->getStatusCode() >= 400) {
            $span->setStatus(StatusCode::STATUS_UNSET);
        } else {
            $span->setStatus(StatusCode::STATUS_OK);
        }

        $span->end();
        Context::storage()->detach();
    }

    /**
     * 创建内部调用跨度（DB 查询、外部 API 等）
     */
    public function startInternalSpan(string $name, array $attributes = []): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $tracer = $this->tracerProvider->getTracer('huwutong-internal', '1.0.0');
        $span = $tracer->spanBuilder($name)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttributes(Attributes::create($attributes))
            ->startSpan();

        return ['tracer' => $tracer, 'span' => $span];
    }

    /**
     * 结束内部调用跨度
     */
    public function endInternalSpan(?array $traceContext): void
    {
        if (!$traceContext || !$this->isEnabled()) {
            return;
        }

        $traceContext['span']->end();
    }

    /**
     * 记录异常到当前跨度
     */
    public function recordException(\Throwable $e, ?array $traceContext = null): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $span = $traceContext['span'] ?? null;
        if (!$span) {
            return;
        }

        $span->recordException($e, Attributes::create([
            'exception.message' => $e->getMessage(),
            'exception.class' => get_class($e),
            'exception.file' => $e->getFile() . ':' . $e->getLine(),
        ]));
        $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
    }

    /**
     * 优雅关闭，刷新所有待发送的跨度
     */
    public function shutdown(): void
    {
        if ($this->tracerProvider) {
            try {
                $this->tracerProvider->shutdown();
            } catch (\Throwable $e) {
                Log::warning('OpenTelemetry 关闭异常', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * 获取追踪后端健康状态
     */
    public function getHealth(): array
    {
        if (!$this->enabled) {
            return [
                'enabled' => false,
                'connected' => false,
                'message' => 'OpenTelemetry 未启用（设置 OTEL_ENABLED=true）',
            ];
        }

        return [
            'enabled' => true,
            'connected' => $this->tracerProvider !== null,
            'service_name' => env('OTEL_SERVICE_NAME', 'huwutong-api'),
            'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://localhost:4318'),
            'sampling_ratio' => $this->samplingRatio,
        ];
    }
}

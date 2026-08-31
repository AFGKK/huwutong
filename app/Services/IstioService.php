<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IstioService
{
    /**
     * 获取 Istio 仪表盘数据
     */
    public function getDashboard(): array
    {
        return [
            'istio_enabled' => config('istio.enabled', false),
            'mtls_enabled' => config('istio.mtls_enabled', true),
            'sidecar_injection' => config('istio.sidecar_injection', 'enabled'),
            'mesh_version' => $this->getMeshVersion(),
            'pilot_status' => $this->checkComponent('pilot'),
            'proxy_count' => $this->getProxyCount(),
            'services_in_mesh' => $this->getServicesInMesh(),
            'total_deployments' => $this->getTotalDeployments(),
            'active_virtual_services' => $this->countResources('VirtualService'),
            'active_destination_rules' => $this->countResources('DestinationRule'),
            'active_gateways' => $this->countResources('Gateway'),
            'active_authorization_policies' => $this->countResources('AuthorizationPolicy'),
        ];
    }

    /**
     * 获取服务拓扑概览
     */
    public function getServiceTopology(): array
    {
        $services = config('istio.services', []);
        $topology = [];

        foreach ($services as $name => $config) {
            $topology[] = [
                'name' => $name,
                'version' => $config['version'] ?? 'v1',
                'port' => $config['port'] ?? 80,
                'protocol' => $config['protocol'] ?? 'http',
                'has_sidecar' => $config['sidecar'] ?? true,
                'mtls_enabled' => $config['mtls'] ?? true,
                'virtual_service' => $config['virtual_service'] ?? null,
                'destination_rule' => $config['destination_rule'] ?? null,
            ];
        }

        return $topology;
    }

    /**
     * 获取流量规则列表
     */
    public function getTrafficRules(): array
    {
        return [
            'virtual_services' => $this->getVirtualServiceSummary(),
            'destination_rules' => $this->getDestinationRuleSummary(),
            'gateways' => $this->getGatewaySummary(),
        ];
    }

    /**
     * 获取安全策略
     */
    public function getSecurityPolicies(): array
    {
        return [
            'mtls_mode' => config('istio.mtls_mode', 'STRICT'),
            'authorization_policies' => $this->getAuthPolicySummary(),
            'peer_authentications' => $this->getPeerAuthSummary(),
        ];
    }

    /**
     * 获取可观测性配置
     */
    public function getObservabilityConfig(): array
    {
        return [
            'tracing_enabled' => config('istio.tracing.enabled', true),
            'tracing_sampling_rate' => config('istio.tracing.sampling_rate', 0.1),
            'metrics_enabled' => config('istio.metrics.enabled', true),
            'access_log_enabled' => config('istio.access_log.enabled', true),
            'grafana_dashboard' => config('istio.observability.grafana_url', ''),
            'jaeger_url' => config('istio.observability.jaeger_url', ''),
            'kiali_url' => config('istio.observability.kiali_url', ''),
        ];
    }

    /**
     * 执行金丝雀发布
     */
    public function canaryDeploy(array $params): array
    {
        $service = $params['service'] ?? '';
        $version = $params['version'] ?? '';
        $weight = $params['weight'] ?? 10;

        if (empty($service) || empty($version)) {
            throw new \InvalidArgumentException(__("app.istio.service_and_version_required"));
        }

        // 记录金丝雀发布配置
        Cache::put("istio:canary:{$service}", [
            'version' => $version,
            'weight' => $weight,
            'started_at' => now()->toIso8601String(),
            'status' => 'in_progress',
        ], now()->addDays(7));

        return [
            'service' => $service,
            'canary_version' => $version,
            'weight' => $weight,
            'command' => "kubectl apply -f deploy/istio/traffic/01-virtual-services.yaml",
            'verify_command' => "curl -H \"x-canary: enabled\" https://admin.huwutong.com",
            'promote_command' => "kubectl set image deployment/{$service} {$service}=huwutong/{$service}:{$version}",
            'rollback_command' => "kubectl apply -f deploy/istio/traffic/01-virtual-services.yaml.bak",
        ];
    }

    /**
     * 切换全量发布
     */
    public function promoteCanary(string $service): array
    {
        $canary = Cache::get("istio:canary:{$service}");
        if (!$canary) {
            throw new \RuntimeException(__("app.istio.msg_0156daef"));
        }

        Cache::put("istio:canary:{$service}", array_merge($canary, [
            'status' => 'promoted',
            'promoted_at' => now()->toIso8601String(),
        ]), now()->addDays(30));

        return [
            'service' => $service,
            'version' => $canary['version'],
            'status' => 'promoted',
            'command' => "kubectl set image deployment/{$service}-stable {$service}=huwutong/{$service}:{$canary['version']}",
        ];
    }

    /**
     * 回滚金丝雀
     */
    public function rollbackCanary(string $service): array
    {
        Cache::forget("istio:canary:{$service}");

        return [
            'service' => $service,
            'status' => 'rolled_back',
            'command' => 'kubectl rollout undo deployment/' . $service,
        ];
    }

    /**
     * 获取金丝雀发布列表
     */
    public function getCanaryDeployments(): array
    {
        $prefix = 'istio:canary:';
        $keys = Cache::get('istio:canary:keys', []);

        $deployments = [];
        foreach ($keys as $key) {
            $data = Cache::get("{$prefix}{$key}");
            if ($data) {
                $deployments[] = [
                    'service' => $key,
                    'canary_version' => $data['version'] ?? '',
                    'weight' => $data['weight'] ?? 0,
                    'status' => $data['status'] ?? 'unknown',
                    'started_at' => $data['started_at'] ?? null,
                    'promoted_at' => $data['promoted_at'] ?? null,
                ];
            }
        }

        return $deployments;
    }

    /**
     * 获取部署命令参考
     */
    public function getDeploymentGuide(): array
    {
        return [
            'install_istio' => 'istioctl install --set profile=default -y',
            'enable_injection' => 'kubectl label namespace huwutong istio-injection=enabled',
            'deploy_all' => 'kubectl apply -k deploy/istio/',
            'deploy_gateway' => 'kubectl apply -f deploy/istio/gateway/',
            'deploy_security' => 'kubectl apply -f deploy/istio/security/',
            'deploy_traffic' => 'kubectl apply -f deploy/istio/traffic/',
            'deploy_observability' => 'kubectl apply -f deploy/istio/observability/',
            'dashboard_kiali' => 'istioctl dashboard kiali',
            'dashboard_grafana' => 'istioctl dashboard grafana',
            'dashboard_jaeger' => 'istioctl dashboard jaeger',
            'proxy_status' => 'istioctl proxy-status',
        ];
    }

    // ─── 私有辅助方法 ───

    private function getMeshVersion(): string
    {
        return config('istio.version', '1.21.x');
    }

    private function checkComponent(string $component): string
    {
        return Cache::get("istio:component:{$component}", 'unknown');
    }

    private function getProxyCount(): int
    {
        return count(config('istio.services', []));
    }

    private function getServicesInMesh(): int
    {
        return count(config('istio.services', []));
    }

    private function getTotalDeployments(): int
    {
        return count(config('istio.services', [])) + 2; // + mysql + redis
    }

    private function countResources(string $kind): int
    {
        return Cache::get("istio:resource:{$kind}", 0);
    }

    private function getVirtualServiceSummary(): array
    {
        $services = config('istio.services', []);
        $vss = [];
        foreach ($services as $name => $config) {
            if (!empty($config['virtual_service'])) {
                $vss[] = [
                    'name' => "{$name}-vs",
                    'hosts' => [$config['virtual_service']],
                    'service' => $name,
                ];
            }
        }
        return $vss;
    }

    private function getDestinationRuleSummary(): array
    {
        $services = config('istio.services', []);
        $drs = [];
        foreach ($services as $name => $config) {
            if (!empty($config['destination_rule'])) {
                $drs[] = [
                    'name' => "{$name}-dr",
                    'host' => $name,
                    'traffic_policy' => [
                        'connection_pool' => ['tcp' => ['max_connections' => 100]],
                        'circuit_breaker' => ['max_pending_requests' => 10000],
                    ],
                ];
            }
        }
        return $drs;
    }

    private function getGatewaySummary(): array
    {
        return [
            'name' => 'hwt-ingress-gateway',
            'hosts' => ['admin.huwutong.com', 'portal.huwutong.com', 'api.huwutong.com', 'status.huwutong.com', 'ws.huwutong.com'],
            'ports' => [80, 443],
            'tls_mode' => 'SIMPLE',
        ];
    }

    private function getAuthPolicySummary(): array
    {
        return [
            [
                'name' => 'hwt-api-authz',
                'action' => 'ALLOW',
                'rules' => 'service-level authorization',
                'principals' => ['cluster.local/ns/huwutong/sa/hwt-api'],
            ],
        ];
    }

    private function getPeerAuthSummary(): array
    {
        return [
            [
                'name' => 'default',
                'namespace' => 'huwutong',
                'mtls_mode' => config('istio.mtls_mode', 'STRICT'),
            ],
        ];
    }
}

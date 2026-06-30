<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * API 网关统一层服务 (M1.3-20)
 *
 * 管理 Kong/APISIX API 网关的：
 * - 路由同步（Laravel → 网关）
 * - 插件配置（限流/IP黑名单/CORS/SSL等）
 * - 健康检查
 * - 节点状态监控
 * - 声明式配置导出
 */
class ApiGatewayService
{
    /**
     * 当前引擎
     */
    protected string $engine;

    /**
     * 管理 API 基础 URL
     */
    protected ?string $adminBaseUrl = null;

    /**
     * 管理 API Key
     */
    protected ?string $adminApiKey = null;

    public function __construct()
    {
        $this->engine = config('api-gateway.engine', 'none');
        $this->initAdminConnection();
    }

    /**
     * 初始化管理 API 连接
     */
    protected function initAdminConnection(): void
    {
        if ($this->engine === 'none') {
            return;
        }

        $adminConfig = config("api-gateway.admin_api.{$this->engine}");
        $this->adminBaseUrl = $adminConfig['base_url'] ?? null;
        $this->adminApiKey = $adminConfig['api_key'] ?? null;
    }

    /**
     * 检查网关是否可用
     */
    public function isAvailable(): bool
    {
        if ($this->engine === 'none') {
            return false;
        }

        try {
            $response = $this->httpGet($this->adminBaseUrl);
            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }

    // ─── 仪表盘 ────────────────────────────────────

    /**
     * 获取网关状态仪表盘
     */
    public function getDashboard(): array
    {
        return Cache::remember('gateway:dashboard', config('api-gateway.monitoring.stats_ttl', 300), function () {
            $engine = $this->engine;
            $available = $this->isAvailable();
            $info = $available ? $this->getEngineInfo() : [];
            $routes = $available ? $this->getRoutes() : [];
            $services = $available ? $this->getServices() : [];
            $upstreams = $available ? $this->getUpstreams() : [];
            $plugins = $available ? $this->getPlugins() : [];

            return [
                'engine' => $engine,
                'available' => $available,
                'version' => $info['version'] ?? 'N/A',
                'node_count' => $info['node_count'] ?? 0,
                'stats' => [
                    'routes' => count($routes),
                    'services' => count($services),
                    'upstreams' => count($upstreams),
                    'plugins' => count($plugins),
                ],
                'config' => [
                    'route_sync' => config('api-gateway.route_sync.enabled', false),
                    'rate_limiting' => config('api-gateway.plugins.rate_limiting.enabled', true),
                    'ip_restriction' => config('api-gateway.plugins.ip_restriction.enabled', true),
                    'cors' => config('api-gateway.plugins.cors.enabled', true),
                    'ssl' => config('api-gateway.plugins.ssl.enabled', true),
                    'logging' => config('api-gateway.plugins.logging.enabled', true),
                ],
            ];
        });
    }

    /**
     * 清除仪表盘缓存
     */
    public function clearCache(): void
    {
        Cache::forget('gateway:dashboard');
    }

    // ─── 引擎信息 ──────────────────────────────────

    /**
     * 获取引擎信息
     */
    public function getEngineInfo(): array
    {
        if (!$this->ensureAvailable()) {
            return ['version' => 'N/A', 'node_count' => 0];
        }

        try {
            return match ($this->engine) {
                'kong' => $this->getKongInfo(),
                'apisix' => $this->getApisixInfo(),
                default => ['version' => 'N/A', 'node_count' => 0],
            };
        } catch (Exception $e) {
            Log::warning("获取网关引擎信息失败: {$e->getMessage()}");
            return ['version' => 'N/A', 'node_count' => 0];
        }
    }

    /**
     * 获取 Kong 信息
     */
    protected function getKongInfo(): array
    {
        $response = $this->httpGet("{$this->adminBaseUrl}");
        $data = $response->json();

        return [
            'version' => $data['version'] ?? 'N/A',
            'hostname' => $data['hostname'] ?? 'N/A',
            'node_count' => $data['node_count'] ?? 0,
            'database' => $data['configuration']['database'] ?? 'N/A',
            'plugins' => $data['plugins']['available_on_server'] ?? [],
            'timers' => $data['timers']['running'] ?? 0,
        ];
    }

    /**
     * 获取 APISIX 信息
     */
    protected function getApisixInfo(): array
    {
        $response = $this->httpGet("{$this->adminBaseUrl}/apisix/admin");
        return [
            'version' => 'APISIX',
            'node_count' => 1,
        ];
    }

    // ─── 路由管理 ──────────────────────────────────

    /**
     * 获取路由列表
     */
    public function getRoutes(): array
    {
        if (!$this->ensureAvailable()) {
            return [];
        }

        try {
            return match ($this->engine) {
                'kong' => $this->getKongRoutes(),
                'apisix' => $this->getApisixRoutes(),
                default => [],
            };
        } catch (Exception $e) {
            Log::warning("获取路由列表失败: {$e->getMessage()}");
            return [];
        }
    }

    protected function getKongRoutes(): array
    {
        $response = $this->httpGet("{$this->adminBaseUrl}/routes");
        $data = $response->json();
        $routes = $data['data'] ?? [];

        return array_map(fn ($r) => [
            'id' => $r['id'] ?? '',
            'name' => $r['name'] ?? '',
            'paths' => $r['paths'] ?? [],
            'methods' => $r['methods'] ?? [],
            'hosts' => $r['hosts'] ?? [],
            'protocols' => $r['protocols'] ?? ['http'],
            'service' => $r['service']['id'] ?? null,
            'plugins' => $r['plugins'] ?? [],
            'strip_path' => $r['strip_path'] ?? true,
            'created_at' => $r['created_at'] ?? 0,
            'enabled' => $r['enabled'] ?? true,
        ], $routes);
    }

    protected function getApisixRoutes(): array
    {
        $response = $this->httpGet("{$this->adminBaseUrl}/apisix/admin/routes");
        $data = $response->json();
        $routes = $data['list'] ?? $data['data']['list'] ?? [];

        return array_map(fn ($r) => [
            'id' => $r['id'] ?? '',
            'name' => $r['name'] ?? $r['desc'] ?? '',
            'paths' => $r['uri'] ? [$r['uri']] : ($r['uris'] ?? []),
            'methods' => $r['methods'] ?? [],
            'hosts' => $r['hosts'] ?? [],
            'protocols' => ['http', 'https'],
            'service' => $r['service_id'] ?? null,
            'plugins' => $r['plugins'] ?? [],
            'strip_path' => $r['strip_path'] ?? true,
            'created_at' => $r['create_time'] ?? 0,
            'enabled' => $r['status'] === 1,
        ], $routes ?? []);
    }

    /**
     * 同步路由到网关
     */
    public function syncRoutes(): array
    {
        if (!$this->ensureAvailable()) {
            return ['success' => false, 'error' => '网关不可用'];
        }

        $prefixes = config('api-gateway.route_sync.prefixes', ['api']);
        $upstreamUrl = config('api-gateway.route_sync.upstream_url', 'http://localhost:8000');
        $synced = 0;
        $errors = [];

        // 获取 Laravel API 路由列表
        $routes = $this->collectApiRoutes();

        foreach ($routes as $route) {
            $path = $route['uri'];
            $methods = $route['methods'];

            // 检查是否在同步前缀范围内
            $matched = false;
            foreach ($prefixes as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                continue;
            }

            try {
                $result = match ($this->engine) {
                    'kong' => $this->syncKongRoute($path, $methods, $upstreamUrl),
                    'apisix' => $this->syncApisixRoute($path, $methods, $upstreamUrl),
                    default => false,
                };

                if ($result) {
                    $synced++;
                }
            } catch (Exception $e) {
                $errors[] = "{$path}: {$e->getMessage()}";
            }
        }

        $this->clearCache();

        return [
            'success' => count($errors) === 0,
            'synced' => $synced,
            'total' => count($routes),
            'errors' => $errors,
        ];
    }

    /**
     * 收集 Laravel API 路由
     */
    protected function collectApiRoutes(): array
    {
        $routes = [];
        /** @var \Illuminate\Routing\RouteCollection $routeCollection */
        $routeCollection = app('router')->getRoutes();

        foreach ($routeCollection as $route) {
            $uri = $route->uri();
            $methods = $route->methods();

            // 过滤非 API 路由
            if (!str_starts_with($uri, 'api/')) {
                continue;
            }

            // 过滤 OPTIONS 方法
            $methods = array_values(array_filter($methods, fn ($m) => $m !== 'OPTIONS' && $m !== 'HEAD'));

            if (empty($methods)) {
                continue;
            }

            $routes[] = [
                'uri' => $uri,
                'methods' => $methods,
                'name' => $route->getName() ?? '',
                'middleware' => $route->gatherMiddleware(),
            ];
        }

        return $routes;
    }

    protected function syncKongRoute(string $path, array $methods, string $upstreamUrl): bool
    {
        // 先查找或创建 Service
        $serviceName = 'huwutong-api';
        $serviceId = $this->ensureKongService($serviceName, $upstreamUrl);

        // 创建/更新 Route
        $path = '/' . $path;
        $routeName = 'hwt_' . str_replace(['/', '{', '}'], ['_', '', ''], trim($path, '/'));

        $payload = [
            'name' => $routeName,
            'paths' => [$path],
            'methods' => $methods,
            'service' => ['id' => $serviceId],
            'strip_path' => config('api-gateway.route_sync.strip_prefix', true),
        ];

        // 检查是否已存在
        $existing = $this->httpGet("{$this->adminBaseUrl}/routes", ['name' => $routeName]);
        $existingData = $existing->json();

        if (!empty($existingData['data'])) {
            $existingId = $existingData['data'][0]['id'];
            $this->httpPatch("{$this->adminBaseUrl}/routes/{$existingId}", $payload);
        } else {
            $this->httpPost("{$this->adminBaseUrl}/routes", $payload);
        }

        return true;
    }

    protected function ensureKongService(string $name, string $url): string
    {
        // 检查 Service 是否存在
        $existing = $this->httpGet("{$this->adminBaseUrl}/services", ['name' => $name]);
        $data = $existing->json();

        if (!empty($data['data'])) {
            return $data['data'][0]['id'];
        }

        // 创建 Service
        $response = $this->httpPost("{$this->adminBaseUrl}/services", [
            'name' => $name,
            'url' => $url,
            'connect_timeout' => config('api-gateway.route_sync.connect_timeout', 60000),
            'write_timeout' => config('api-gateway.route_sync.send_timeout', 60000),
            'read_timeout' => config('api-gateway.route_sync.read_timeout', 60000),
            'retries' => config('api-gateway.route_sync.retries', 3),
        ]);

        return $response->json()['id'] ?? '';
    }

    protected function syncApisixRoute(string $path, array $methods, string $upstreamUrl): bool
    {
        $path = '/' . $path;
        $routeId = 'hwt_' . md5($path . implode(',', $methods));

        $payload = [
            'uri' => $path . '*',
            'methods' => $methods,
            'upstream' => [
                'type' => 'roundrobin',
                'nodes' => [
                    $upstreamUrl => 1,
                ],
            ],
            'status' => 1,
        ];

        $this->httpPut("{$this->adminBaseUrl}/apisix/admin/routes/{$routeId}", $payload);
        return true;
    }

    // ─── 服务管理 ──────────────────────────────────

    /**
     * 获取服务列表
     */
    public function getServices(): array
    {
        if (!$this->ensureAvailable()) {
            return [];
        }

        try {
            return match ($this->engine) {
                'kong' => $this->getKongServices(),
                'apisix' => [],
                default => [],
            };
        } catch (Exception $e) {
            return [];
        }
    }

    protected function getKongServices(): array
    {
        $response = $this->httpGet("{$this->adminBaseUrl}/services");
        $data = $response->json();

        return array_map(fn ($s) => [
            'id' => $s['id'] ?? '',
            'name' => $s['name'] ?? '',
            'host' => $s['host'] ?? '',
            'port' => $s['port'] ?? 80,
            'protocol' => $s['protocol'] ?? 'http',
            'path' => $s['path'] ?? '',
            'connect_timeout' => $s['connect_timeout'] ?? 0,
            'write_timeout' => $s['write_timeout'] ?? 0,
            'read_timeout' => $s['read_timeout'] ?? 0,
            'retries' => $s['retries'] ?? 0,
            'created_at' => $s['created_at'] ?? 0,
        ], $data['data'] ?? []);
    }

    // ─── Upstream 管理 ────────────────────────────

    /**
     * 获取 Upstream 列表
     */
    public function getUpstreams(): array
    {
        if (!$this->ensureAvailable()) {
            return [];
        }

        try {
            return match ($this->engine) {
                'kong' => $this->getKongUpstreams(),
                'apisix' => [],
                default => [],
            };
        } catch (Exception $e) {
            return [];
        }
    }

    protected function getKongUpstreams(): array
    {
        $response = $this->httpGet("{$this->adminBaseUrl}/upstreams");
        $data = $response->json();

        return array_map(fn ($u) => [
            'id' => $u['id'] ?? '',
            'name' => $u['name'] ?? '',
            'algorithm' => $u['algorithm'] ?? 'round-robin',
            'healthchecks' => $u['healthchecks'] ?? [],
            'targets_count' => count($u['targets'] ?? []),
            'created_at' => $u['created_at'] ?? 0,
        ], $data['data'] ?? []);
    }

    // ─── 插件管理 ──────────────────────────────────

    /**
     * 获取插件列表
     */
    public function getPlugins(): array
    {
        if (!$this->ensureAvailable()) {
            return [];
        }

        try {
            return match ($this->engine) {
                'kong' => $this->getKongPlugins(),
                'apisix' => [],
                default => [],
            };
        } catch (Exception $e) {
            return [];
        }
    }

    protected function getKongPlugins(): array
    {
        $response = $this->httpGet("{$this->adminBaseUrl}/plugins");
        $data = $response->json();

        return array_map(fn ($p) => [
            'id' => $p['id'] ?? '',
            'name' => $p['name'] ?? '',
            'service' => $p['service']['id'] ?? null,
            'route' => $p['route']['id'] ?? null,
            'consumer' => $p['consumer']['id'] ?? null,
            'config' => $p['config'] ?? [],
            'enabled' => $p['enabled'] ?? true,
            'created_at' => $p['created_at'] ?? 0,
        ], $data['data'] ?? []);
    }

    // ─── 健康检查 ──────────────────────────────────

    /**
     * 健康检查
     */
    public function healthCheck(): array
    {
        if ($this->engine === 'none') {
            return [
                'engine' => 'none',
                'status' => 'disabled',
                'message' => 'API 网关未启用（直连模式）',
            ];
        }

        $available = $this->isAvailable();
        $latency = -1;

        if ($available) {
            $start = microtime(true);
            $this->httpGet($this->adminBaseUrl);
            $latency = (microtime(true) - $start) * 1000;
        }

        return [
            'engine' => $this->engine,
            'status' => $available ? 'healthy' : 'unhealthy',
            'latency_ms' => round($latency, 2),
            'admin_api' => $this->adminBaseUrl,
            'is_connected' => $available,
        ];
    }

    // ─── 声明式配置 ────────────────────────────────

    /**
     * 导出声明式配置
     */
    public function exportDeclarativeConfig(): array
    {
        $syncConfig = config('api-gateway.route_sync');
        $plugins = config('api-gateway.plugins');
        $upstreamUrl = $syncConfig['upstream_url'] ?? 'http://localhost:8000';

        $config = [
            '_format_version' => '3.0',
            '_transform' => true,
            'services' => [
                [
                    'name' => 'huwutong-api',
                    'host' => parse_url($upstreamUrl, PHP_URL_HOST) ?: 'localhost',
                    'port' => parse_url($upstreamUrl, PHP_URL_PORT) ?: 8000,
                    'protocol' => parse_url($upstreamUrl, PHP_URL_SCHEME) ?: 'http',
                    'connect_timeout' => $syncConfig['connect_timeout'] ?? 60000,
                    'write_timeout' => $syncConfig['send_timeout'] ?? 60000,
                    'read_timeout' => $syncConfig['read_timeout'] ?? 60000,
                    'retries' => $syncConfig['retries'] ?? 3,
                    'routes' => [],
                    'plugins' => [],
                ],
            ],
            'upstreams' => [],
            'consumers' => [],
            'plugins' => [],
        ];

        // 添加全局插件
        if ($plugins['rate_limiting']['enabled'] ?? true) {
            $config['plugins'][] = [
                'name' => 'rate-limiting',
                'config' => [
                    'minute' => $plugins['rate_limiting']['limits']['default']['minute'] ?? 600,
                    'hour' => $plugins['rate_limiting']['limits']['default']['hour'] ?? 30000,
                    'policy' => $plugins['rate_limiting']['policy'] ?? 'local',
                ],
            ];
        }

        if ($plugins['cors']['enabled'] ?? true) {
            $config['plugins'][] = [
                'name' => 'cors',
                'config' => [
                    'origins' => $plugins['cors']['origins'] ?? ['*'],
                    'methods' => $plugins['cors']['methods'] ?? 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
                    'headers' => $plugins['cors']['headers'] ?? 'Content-Type,Authorization',
                    'credentials' => $plugins['cors']['credentials'] ?? false,
                    'max_age' => $plugins['cors']['max_age'] ?? 3600,
                ],
            ];
        }

        return $config;
    }

    /**
     * 获取配置状态摘要
     */
    public function getConfigStatus(): array
    {
        return [
            'engine' => $this->engine,
            'route_sync' => config('api-gateway.route_sync.enabled', false),
            'rate_limiting' => config('api-gateway.plugins.rate_limiting.enabled', true),
            'ip_restriction' => config('api-gateway.plugins.ip_restriction.enabled', true),
            'cors' => config('api-gateway.plugins.cors.enabled', true),
            'ssl' => config('api-gateway.plugins.ssl.enabled', true),
            'logging' => config('api-gateway.plugins.logging.enabled', true),
            'prometheus' => config('api-gateway.plugins.prometheus.enabled', false),
            'auth_unloading' => config('api-gateway.plugins.auth_unloading.enabled', false),
            'proxy_cache' => config('api-gateway.plugins.proxy_cache.enabled', false),
            'healthcheck' => config('api-gateway.healthcheck.enabled', true),
        ];
    }

    // ─── HTTP 客户端辅助 ──────────────────────────

    /**
     * 确保网关可用
     */
    protected function ensureAvailable(): bool
    {
        if ($this->engine === 'none' || !$this->adminBaseUrl) {
            return false;
        }
        return true;
    }

    protected function httpGet(string $url, array $params = []): \Illuminate\Http\Client\Response
    {
        return Http::timeout(config("api-gateway.admin_api.{$this->engine}.timeout", 10))
            ->withHeaders($this->getHeaders())
            ->get($url, $params);
    }

    protected function httpPost(string $url, array $data): \Illuminate\Http\Client\Response
    {
        return Http::timeout(config("api-gateway.admin_api.{$this->engine}.timeout", 10))
            ->withHeaders($this->getHeaders())
            ->post($url, $data);
    }

    protected function httpPut(string $url, array $data): \Illuminate\Http\Client\Response
    {
        return Http::timeout(config("api-gateway.admin_api.{$this->engine}.timeout", 10))
            ->withHeaders($this->getHeaders())
            ->put($url, $data);
    }

    protected function httpPatch(string $url, array $data): \Illuminate\Http\Client\Response
    {
        return Http::timeout(config("api-gateway.admin_api.{$this->engine}.timeout", 10))
            ->withHeaders($this->getHeaders())
            ->patch($url, $data);
    }

    protected function getHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($this->adminApiKey) {
            if ($this->engine === 'kong') {
                $headers['Kong-Admin-Token'] = $this->adminApiKey;
            } elseif ($this->engine === 'apisix') {
                $headers['X-API-KEY'] = $this->adminApiKey;
            }
        }

        return $headers;
    }
}

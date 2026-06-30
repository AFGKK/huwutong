<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebSearchService
{
    protected string $provider;
    protected array $config;
    protected int $maxResults;
    protected int $timeout;

    public function __construct()
    {
        $this->config = config('web-search', []);
        $this->provider = $this->config['default'] ?? 'none';
        $this->maxResults = $this->config['max_results'] ?? 5;
        $this->timeout = $this->config['timeout'] ?? 15;
    }

    /**
     * 检查联网搜索是否已配置可用
     */
    public function isAvailable(): bool
    {
        if ($this->provider === 'none') {
            return false;
        }

        $providerConfig = $this->config['providers'][$this->provider] ?? [];
        if (empty($providerConfig)) {
            return false;
        }

        return match ($this->provider) {
            'tavily' => !empty($providerConfig['api_key']),
            'serpapi' => !empty($providerConfig['api_key']),
            'google_cse' => !empty($providerConfig['api_key']) && !empty($providerConfig['cx']),
            'searxng' => !empty($providerConfig['instance_url']),
            default => false,
        };
    }

    /**
     * 获取当前配置的 provider 名称
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * 执行联网搜索
     * @return array{success: bool, results: array, error?: string}
     */
    public function search(string $query, int $maxResults = null): array
    {
        $maxResults = $maxResults ?? $this->maxResults;
        $cacheKey = 'web_search_' . md5($query . '_' . $maxResults);
        $cacheTtl = $this->config['cache_ttl'] ?? 10;

        if ($cacheTtl > 0) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        if (!$this->isAvailable()) {
            $result = [
                'success' => false,
                'results' => [],
                'error' => '联网搜索未配置。请在管理员设置中配置 ' . $this->provider . ' API Key。',
                'provider' => $this->provider,
            ];
            if ($cacheTtl > 0) {
                Cache::put($cacheKey, $result, now()->addMinutes($cacheTtl));
            }
            return $result;
        }

        try {
            $results = match ($this->provider) {
                'tavily' => $this->searchTavily($query, $maxResults),
                'serpapi' => $this->searchSerpApi($query, $maxResults),
                'google_cse' => $this->searchGoogleCse($query, $maxResults),
                'searxng' => $this->searchSearxng($query, $maxResults),
                default => ['success' => false, 'results' => [], 'error' => '未知搜索 Provider'],
            };

            if ($cacheTtl > 0 && ($results['success'] ?? false)) {
                Cache::put($cacheKey, $results, now()->addMinutes($cacheTtl));
            }

            return $results;
        } catch (\Exception $e) {
            Log::warning('Web search failed', [
                'provider' => $this->provider,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'results' => [],
                'error' => '搜索请求失败：' . $e->getMessage(),
                'provider' => $this->provider,
            ];
        }
    }

    /**
     * 搜索并返回格式化后的上下文文本（供 LLM 使用）
     */
    public function searchAsContext(string $query, int $maxResults = null): string
    {
        $result = $this->search($query, $maxResults);

        if (!$result['success'] || empty($result['results'])) {
            return '';
        }

        $context = "【联网搜索结果】\n\n";
        foreach ($result['results'] as $i => $item) {
            $context .= "[来源 {$i}] " . ($item['title'] ?? '无标题') . "\n";
            $context .= "链接: " . ($item['url'] ?? '#') . "\n";
            $context .= "内容: " . ($item['snippet'] ?? $item['content'] ?? '') . "\n\n";
        }

        return $context;
    }

    // ── Tavily Search ──

    protected function searchTavily(string $query, int $maxResults): array
    {
        $config = $this->config['providers']['tavily'];
        $response = Http::timeout($this->timeout)->post($config['api_url'], [
            'api_key' => $config['api_key'],
            'query' => $query,
            'max_results' => $maxResults,
            'include_answer' => false,
        ]);

        if (!$response->successful()) {
            return ['success' => false, 'results' => [], 'error' => 'Tavily API 返回错误: ' . $response->status()];
        }

        $data = $response->json();
        $results = [];
        foreach ($data['results'] ?? [] as $item) {
            $results[] = [
                'title' => $item['title'] ?? '',
                'url' => $item['url'] ?? '',
                'content' => $item['content'] ?? '',
                'score' => $item['score'] ?? 0,
            ];
        }

        return [
            'success' => true,
            'results' => $results,
            'provider' => 'tavily',
            'query' => $query,
        ];
    }

    // ── SerpAPI ──

    protected function searchSerpApi(string $query, int $maxResults): array
    {
        $config = $this->config['providers']['serpapi'];
        $response = Http::timeout($this->timeout)->get($config['api_url'], [
            'api_key' => $config['api_key'],
            'q' => $query,
            'num' => $maxResults,
            'hl' => 'zh-CN',
        ]);

        if (!$response->successful()) {
            return ['success' => false, 'results' => [], 'error' => 'SerpAPI 返回错误: ' . $response->status()];
        }

        $data = $response->json();
        $results = [];
        foreach ($data['organic_results'] ?? [] as $item) {
            $results[] = [
                'title' => $item['title'] ?? '',
                'url' => $item['link'] ?? '',
                'snippet' => $item['snippet'] ?? '',
            ];
        }

        return [
            'success' => true,
            'results' => $results,
            'provider' => 'serpapi',
            'query' => $query,
        ];
    }

    // ── Google Custom Search ──

    protected function searchGoogleCse(string $query, int $maxResults): array
    {
        $config = $this->config['providers']['google_cse'];
        $response = Http::timeout($this->timeout)->get($config['api_url'], [
            'key' => $config['api_key'],
            'cx' => $config['cx'],
            'q' => $query,
            'num' => min($maxResults, 10),
            'hl' => 'zh-CN',
        ]);

        if (!$response->successful()) {
            return ['success' => false, 'results' => [], 'error' => 'Google CSE 返回错误: ' . $response->status()];
        }

        $data = $response->json();
        $results = [];
        foreach ($data['items'] ?? [] as $item) {
            $results[] = [
                'title' => $item['title'] ?? '',
                'url' => $item['link'] ?? '',
                'snippet' => $item['snippet'] ?? '',
            ];
        }

        return [
            'success' => true,
            'results' => $results,
            'provider' => 'google_cse',
            'query' => $query,
        ];
    }

    // ── SearXNG ──

    protected function searchSearxng(string $query, int $maxResults): array
    {
        $config = $this->config['providers']['searxng'];
        $instance = rtrim($config['instance_url'], '/');

        $response = Http::timeout($this->timeout)->get($instance . $config['api_url'], [
            'q' => $query,
            'format' => 'json',
            'language' => 'zh-CN',
            'categories' => 'general',
            'pageno' => 1,
        ]);

        if (!$response->successful()) {
            return ['success' => false, 'results' => [], 'error' => 'SearXNG 返回错误: ' . $response->status()];
        }

        $data = $response->json();
        $results = [];
        foreach ($data['results'] ?? [] as $item) {
            $results[] = [
                'title' => $item['title'] ?? '',
                'url' => $item['url'] ?? '',
                'content' => $item['content'] ?? $item['snippet'] ?? '',
            ];
        }

        return [
            'success' => true,
            'results' => array_slice($results, 0, $maxResults),
            'provider' => 'searxng',
            'query' => $query,
        ];
    }

    /**
     * 获取支持的 provider 列表及配置状态
     */
    public static function getProviderStatus(): array
    {
        $config = config('web-search', []);
        $current = $config['default'] ?? 'none';
        $providers = [];

        foreach (($config['providers'] ?? []) as $name => $cfg) {
            $configured = match ($name) {
                'tavily' => !empty($cfg['api_key']),
                'serpapi' => !empty($cfg['api_key']),
                'google_cse' => !empty($cfg['api_key']) && !empty($cfg['cx']),
                'searxng' => !empty($cfg['instance_url']),
                default => false,
            };
            $providers[$name] = [
                'configured' => $configured,
                'active' => $name === $current,
            ];
        }

        return [
            'providers' => $providers,
            'active_provider' => $current,
            'available' => (new self)->isAvailable(),
        ];
    }
}

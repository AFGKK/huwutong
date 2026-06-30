<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LinkPreviewService
{
    protected int $timeout = 5;
    protected int $maxBodySize = 512 * 1024; // 512KB

    /**
     * Extract first URL from text
     */
    public function extractUrl(string $text): ?string
    {
        $pattern = '/https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?&\/\/=]*)/';
        if (preg_match($pattern, $text, $matches)) {
            return $matches[0];
        }
        return null;
    }

    /**
     * Fetch Open Graph / link preview data for a URL
     */
    public function getPreview(string $url): ?array
    {
        $cacheKey = 'link_preview_' . md5($url);

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($url) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withOptions(['stream' => true])
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
                    ])
                    ->get($url);

                if (!$response->successful()) {
                    return null;
                }

                $html = $response->body();
                $html = substr($html, 0, $this->maxBodySize);

                return [
                    'url' => $url,
                    'title' => $this->parseMeta($html, 'og:title') ?: $this->parseTitle($html),
                    'description' => $this->parseMeta($html, 'og:description') ?: $this->parseMeta($html, 'description'),
                    'image' => $this->resolveUrl($url, $this->parseMeta($html, 'og:image')),
                    'favicon' => $this->resolveUrl($url, $this->parseFavicon($html)),
                    'site_name' => $this->parseMeta($html, 'og:site_name'),
                    'type' => $this->parseMeta($html, 'og:type') ?: 'website',
                ];
            } catch (\Exception $e) {
                Log::debug('LinkPreview fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Parse Open Graph or meta tag by property/name
     */
    protected function parseMeta(string $html, string $property): ?string
    {
        // Try property="og:title" first
        $pattern = '/<meta[^>]+(?:property|name)=["\']' . preg_quote($property, '/') . '["\']\s+content=["\']([^"\']*)["\'][^>]*\/?>/i';
        if (preg_match($pattern, $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8') ?: null;
        }
        // Try content before property
        $pattern2 = '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+(?:property|name)=["\']' . preg_quote($property, '/') . '["\'][^>]*\/?>/i';
        if (preg_match($pattern2, $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8') ?: null;
        }
        return null;
    }

    /**
     * Parse <title> tag
     */
    protected function parseTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8') ?: null;
        }
        return null;
    }

    /**
     * Parse favicon URL
     */
    protected function parseFavicon(string $html): ?string
    {
        // Try shortcut icon / icon link
        if (preg_match('/<link[^>]+rel=["\'](?:shortcut )?icon["\'][^>]+href=["\']([^"\']*)["\'][^>]*\/?>/i', $html, $matches)) {
            return $matches[1];
        }
        // Try href before rel
        if (preg_match('/<link[^>]+href=["\']([^"\']*)["\'][^>]+rel=["\'](?:shortcut )?icon["\'][^>]*\/?>/i', $html, $matches)) {
            return $matches[1];
        }
        return '/favicon.ico';
    }

    /**
     * Resolve relative URL to absolute
     */
    protected function resolveUrl(string $baseUrl, ?string $maybeRelative): ?string
    {
        if (!$maybeRelative) return null;
        if (str_starts_with($maybeRelative, 'http://') || str_starts_with($maybeRelative, 'https://')) {
            return $maybeRelative;
        }
        if (str_starts_with($maybeRelative, '//')) {
            return 'https:' . $maybeRelative;
        }
        $parsed = parse_url($baseUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        if (str_starts_with($maybeRelative, '/')) {
            return "{$scheme}://{$host}{$maybeRelative}";
        }
        $path = dirname($parsed['path'] ?? '/');
        return "{$scheme}://{$host}{$path}/{$maybeRelative}";
    }
}

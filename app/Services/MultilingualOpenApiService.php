<?php

namespace App\Services;

use App\Models\ApiDocEndpoint;
use App\Models\ApiDocSchema;
use App\Models\ApiDocTag;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

/**
 * 多语言 OpenAPI 文档生成服务 (M2-115)
 *
 * 功能：
 * - 根据 Accept-Language 生成本地化的 OpenAPI 3.0 规范
 * - 端点摘要/描述多语言翻译
 * - Schema 描述多语言翻译
 * - 代码示例多语言适配
 * - 导出 JSON/YAML 格式
 */
class MultilingualOpenApiService
{
    /**
     * 支持的语言列表
     */
    public const SUPPORTED_LOCALES = ['en', 'zh_CN', 'ja'];

    /**
     * 默认语言
     */
    public const DEFAULT_LOCALE = 'en';

    /**
     * 获取当前语言
     */
    public function getLocale(): string
    {
        $locale = App::getLocale();
        return in_array($locale, self::SUPPORTED_LOCALES) ? $locale : self::DEFAULT_LOCALE;
    }

    /**
     * 获取支持的语言列表
     */
    public function getSupportedLocales(): array
    {
        return [
            ['code' => 'en', 'name' => 'English', 'native' => 'English'],
            ['code' => 'zh_CN', 'name' => 'Chinese (Simplified)', 'native' => '简体中文'],
            ['code' => 'ja', 'name' => 'Japanese', 'native' => '日本語'],
        ];
    }

    /**
     * 生成本地化的 OpenAPI 3.0 规范
     */
    public function generateOpenApiSpec(?string $locale = null, array $options = []): array
    {
        $locale = $locale ?: $this->getLocale();
        $originalLocale = App::getLocale();
        App::setLocale($locale);

        try {
            $spec = $this->buildSpec($locale, $options);
        } finally {
            App::setLocale($originalLocale);
        }

        return $spec;
    }

    /**
     * 构建 OpenAPI 规范
     */
    protected function buildSpec(string $locale, array $options): array
    {
        $t = fn(string $key, array $replace = []) => __("api-docs.$key", $replace, $locale);

        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => $t('page_title'),
                'description' => $t('page_description'),
                'version' => $options['api_version'] ?? '1.0.0',
                'x-locale' => $locale,
            ],
            'servers' => $this->getServers($locale),
            'paths' => $this->getPaths($locale, $options),
            'components' => [
                'schemas' => $this->getSchemas($locale),
                'securitySchemes' => $this->getSecuritySchemes($locale),
            ],
            'tags' => $this->getTags($locale),
            'x-translations' => [
                'available_locales' => $this->getSupportedLocales(),
                'current_locale' => $locale,
            ],
        ];

        if (!empty($options['include_x_groups'])) {
            $spec['x-groups'] = $this->getGroups($locale);
        }

        return $spec;
    }

    /**
     * 获取服务器列表
     */
    protected function getServers(string $locale): array
    {
        $t = fn(string $key) => __("api-docs.$key", [], $locale);

        return [
            [
                'url' => config('app.url') . '/api',
                'description' => $t('common.base_url'),
            ],
        ];
    }

    /**
     * 获取所有端点路径
     */
    protected function getPaths(string $locale, array $options): array
    {
        $paths = [];
        $query = ApiDocEndpoint::with('apiVersion')
            ->orderBy('sort_order')
            ->orderBy('path');

        if (!empty($options['api_version_id'])) {
            $query->where('api_version_id', $options['api_version_id']);
        }
        if (!empty($options['group'])) {
            $query->where('group', $options['group']);
        }
        if (!empty($options['status'])) {
            $query->where('status', $options['status']);
        }

        $endpoints = $query->get();

        foreach ($endpoints as $ep) {
            $method = strtolower($ep->method);
            $path = '/' . ltrim($ep->path, '/');

            $localized = $this->getLocalizedEndpoint($ep, $locale);

            $pathItem = [
                $method => [
                    'summary' => $localized['summary'],
                    'description' => $localized['description'],
                    'operationId' => str_replace(['/', '{', '}', '-'], ['_', '', '', '_'], trim($ep->path, '/')) . '_' . $method,
                    'tags' => $ep->tag ? [$ep->tag] : [],
                    'parameters' => $this->localizeParameters($ep->parameters ?? [], $locale),
                    'responses' => $this->localizeResponses($ep->responses ?? [], $locale),
                    'deprecated' => $ep->status === 'deprecated',
                    'x-status' => $ep->status,
                    'x-group' => $ep->group,
                ],
            ];

            // 请求体
            if ($ep->request_body && in_array($ep->method, ['POST', 'PUT', 'PATCH'])) {
                $pathItem[$method]['requestBody'] = [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => $ep->request_body,
                        ],
                    ],
                ];

                // 请求示例
                if (!empty($localized['example_request'])) {
                    $pathItem[$method]['requestBody']['content']['application/json']['example'] = $localized['example_request'];
                }
            }

            // 响应示例
            if (!empty($localized['example_response'])) {
                foreach ($pathItem[$method]['responses'] as $code => &$resp) {
                    $resp['content'] ??= [
                        'application/json' => [
                            'schema' => ['type' => 'object'],
                        ],
                    ];
                    $resp['content']['application/json']['example'] = $localized['example_response'];
                }
            }

            // 代码示例
            $codeExamples = $this->getLocalizedCodeExamples($ep, $locale);
            if (!empty($codeExamples)) {
                $pathItem[$method]['x-code-samples'] = $codeExamples;
            }

            // 安全认证
            if ($ep->security) {
                $pathItem[$method]['security'] = $ep->security;
            }

            $paths[$path] = array_merge($paths[$path] ?? [], $pathItem);
        }

        return $paths;
    }

    /**
     * 获取本地化的端点数据（公开方法供 Controller 调用）
     */
    public function getLocalizedEndpoint(ApiDocEndpoint $ep, string $locale): array
    {
        $result = [
            'summary' => $ep->summary,
            'description' => $ep->description ?: __('api-docs.common.no_description', [], $locale),
            'example_request' => $ep->example_request,
            'example_response' => $ep->example_response,
        ];

        $translations = $ep->translations;
        if (!empty($translations[$locale])) {
            $loc = $translations[$locale];
            $result['summary'] = $loc['summary'] ?? $result['summary'];
            $result['description'] = $loc['description'] ?? $result['description'];
            $result['example_request'] = $loc['example_request'] ?? $result['example_request'];
            $result['example_response'] = $loc['example_response'] ?? $result['example_response'];
        }

        return $result;
    }

    /**
     * 获取本地化的代码示例
     */
    protected function getLocalizedCodeExamples(ApiDocEndpoint $ep, string $locale): array
    {
        $examples = [];

        // 从 code_examples JSON 字段获取
        $codeExamples = $ep->code_examples ?? [];
        foreach ($codeExamples as $example) {
            $lang = $example['language'] ?? 'curl';
            $examples[] = [
                'lang' => $lang,
                'source' => $example['code'] ?? '',
                'label' => $example['label'] ?? $lang,
                'description' => $example['description'] ?? '',
            ];
        }

        // 从 code_snippets 表获取（含 locale 过滤）
        $snippets = $ep->snippets()
            ->where(function ($q) use ($locale) {
                $q->where('locale', $locale)
                  ->orWhere('locale', 'en');
            })
            ->orderBy('locale', 'desc') // 优先当前语言
            ->orderBy('sort_order')
            ->get();

        $seen = [];
        foreach ($snippets as $snippet) {
            $key = $snippet->language . '_' . $snippet->locale;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $examples[] = [
                'lang' => $snippet->language,
                'source' => $snippet->code,
                'label' => $snippet->title ?: $snippet->language,
                'description' => $snippet->description,
            ];
        }

        return $examples;
    }

    /**
     * 本地化参数
     */
    protected function localizeParameters(array $parameters, string $locale): array
    {
        return array_map(function ($param) use ($locale) {
            if (isset($param['description'])) {
                // 检查是否有翻译
                $translations = $param['x-translations'] ?? [];
                if (!empty($translations[$locale]['description'])) {
                    $param['description'] = $translations[$locale]['description'];
                }
            }
            return $param;
        }, $parameters);
    }

    /**
     * 本地化响应
     */
    protected function localizeResponses(array $responses, string $locale): array
    {
        $result = [];
        foreach ($responses as $code => $resp) {
            $result[$code] = $resp;
            if (isset($resp['description'])) {
                $translations = $resp['x-translations'] ?? [];
                if (!empty($translations[$locale]['description'])) {
                    $result[$code]['description'] = $translations[$locale]['description'];
                }
            }
        }
        return $result;
    }

    /**
     * 获取所有 Schema（含本地化）
     */
    protected function getSchemas(string $locale): array
    {
        $schemas = [];
        $records = ApiDocSchema::orderBy('name')->get();

        foreach ($records as $record) {
            $description = $record->description;
            $properties = $record->properties;

            $translations = $record->translations ?? [];
            if (!empty($translations[$locale])) {
                $description = $translations[$locale]['description'] ?? $description;
                if (!empty($translations[$locale]['properties'])) {
                    $properties = array_merge($properties ?? [], $translations[$locale]['properties']);
                }
            }

            $schema = $record->schema ?: ['type' => 'object', 'properties' => []];
            $schema['description'] = $description;

            // 本地化属性描述
            if ($properties && isset($schema['properties'])) {
                foreach ($schema['properties'] as $propName => &$propDef) {
                    if (is_array($propDef) && isset($properties[$propName])) {
                        $propDef['description'] = $properties[$propName];
                    }
                }
            }

            $schemas[$record->name] = $schema;

            if ($record->example) {
                $schema['example'] = $record->example;
            }
        }

        return $schemas;
    }

    /**
     * 获取安全认证方案
     */
    protected function getSecuritySchemes(string $locale): array
    {
        $t = fn(string $key) => __("api-docs.$key", [], $locale);

        return [
            'BearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'Sanctum Token',
                'description' => $t('common.authorization'),
            ],
            'ApiKeyAuth' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'X-API-Key',
                'description' => 'API Key authentication',
            ],
        ];
    }

    /**
     * 获取标签列表（含本地化）
     */
    protected function getTags(string $locale): array
    {
        $tags = [];
        $records = ApiDocTag::orderBy('sort_order')->get();

        foreach ($records as $record) {
            $label = $record->label;
            $description = $record->description;

            $translations = $record->translations ?? [];
            if (!empty($translations[$locale])) {
                $label = $translations[$locale]['label'] ?? $label;
                $description = $translations[$locale]['description'] ?? $description;
            }

            $tags[] = [
                'name' => $record->name,
                'description' => $description,
                'x-label' => $label,
            ];
        }

        return $tags;
    }

    /**
     * 获取分组列表（含本地化）
     */
    protected function getGroups(string $locale): array
    {
        $groups = [];
        $defaultGroups = __('api-docs.groups', [], $locale);

        // 从数据库中已有的分组获取
        $dbGroups = ApiDocEndpoint::select('group')
            ->whereNotNull('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group');

        foreach ($dbGroups as $group) {
            $groups[$group] = $defaultGroups[$group] ?? $group;
        }

        return $groups;
    }

    /**
     * 根据 Accept-Language 解析最佳语言
     */
    public static function resolveLocale(?string $acceptLanguage): string
    {
        if (empty($acceptLanguage)) {
            return self::DEFAULT_LOCALE;
        }

        $locales = [];
        foreach (explode(',', $acceptLanguage) as $part) {
            $parts = explode(';', trim($part));
            $locale = trim($parts[0]);
            $quality = 1.0;
            if (isset($parts[1]) && str_starts_with($parts[1], 'q=')) {
                $quality = (float) substr($parts[1], 2);
            }
            $locales[$locale] = $quality;
        }

        arsort($locales);

        $map = [
            'en' => 'en', 'en-US' => 'en', 'en-GB' => 'en',
            'zh' => 'zh_CN', 'zh-CN' => 'zh_CN', 'zh-Hans' => 'zh_CN', 'zh-Hans-CN' => 'zh_CN',
            'ja' => 'ja', 'ja-JP' => 'ja',
        ];

        foreach (array_keys($locales) as $locale) {
            $normalized = $map[$locale] ?? null;
            if ($normalized && in_array($normalized, self::SUPPORTED_LOCALES)) {
                return $normalized;
            }
            // 尝试前缀匹配
            $prefix = substr($locale, 0, 2);
            if (isset($map[$prefix]) && in_array($map[$prefix], self::SUPPORTED_LOCALES)) {
                return $map[$prefix];
            }
        }

        return self::DEFAULT_LOCALE;
    }
}

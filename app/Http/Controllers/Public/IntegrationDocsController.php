<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\ApiDocsService;
use App\Services\ErrorCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntegrationDocsController extends Controller
{
    public function hub(): View
    {
        return view('public.docs-hub', [
            'links' => config('integration-docs.hub_links', []),
            'examples' => config('integration-docs.examples', []),
            'sdks' => config('sdk-docs.sdks', []),
        ]);
    }

    public function apiDocs(): View
    {
        return view('public.api-docs', [
            'auth' => config('integration-docs.auth', []),
            'groups' => config('integration-docs.api_groups', []),
            'baseUrl' => config('integration-docs.base_url'),
            'examples' => config('integration-docs.examples', []),
        ]);
    }

    public function errorCodes(): View
    {
        $codes = config('integration-docs.error_codes', []);

        try {
            $live = app(ErrorCodeService::class)->all();
            if (is_array($live) && count($live) > 0) {
                $mapped = [];
                foreach ($live as $item) {
                    if (is_array($item)) {
                        $mapped[] = [
                            'code' => $item['code'] ?? ($item['value'] ?? ''),
                            'http' => $item['http_status'] ?? ($item['http'] ?? ''),
                            'message' => $item['message'] ?? '',
                        ];
                    }
                }
                if ($mapped !== []) {
                    $codes = $mapped;
                }
            }
        } catch (\Throwable) {
            // keep config fallback
        }

        return view('public.error-codes', [
            'codes' => $codes,
        ]);
    }

    public function webhooks(): View
    {
        return view('public.webhooks', [
            'webhooks' => config('integration-docs.webhooks', []),
        ]);
    }

    public function quickstart(): View
    {
        $sdks = collect(config('sdk-docs.sdks', []))
            ->only(['php', 'node', 'python', 'go', 'java', 'csharp'])
            ->values()
            ->all();

        return view('public.quickstart', [
            'sdks' => $sdks,
            'examples' => config('integration-docs.examples', []),
            'hubLinks' => config('integration-docs.hub_links', []),
        ]);
    }

    /**
     * 公开 API 文档 JSON：优先 DB active 端点，否则回退配置。
     */
    public function publicApiJson(Request $request): JsonResponse
    {
        $version = $request->input('version');
        $source = 'config';
        $payload = [
            'version' => [
                'version' => 'v1',
                'name' => 'Public License API',
                'status' => 'active',
                'base_path' => '/api',
            ],
            'groups' => config('integration-docs.api_groups', []),
            'auth' => config('integration-docs.auth', []),
            'base_url' => config('integration-docs.base_url'),
        ];

        try {
            $dbDocs = app(ApiDocsService::class)->getPublicDocs($version);
            if (($dbDocs['total_endpoints'] ?? 0) > 0) {
                $source = 'database';
                $payload['version'] = $dbDocs['version'] ?? $payload['version'];
                $payload['groups'] = $dbDocs['groups'];
                $payload['total_endpoints'] = $dbDocs['total_endpoints'];
            }
        } catch (\Throwable) {
            // keep config
        }

        if (! isset($payload['total_endpoints'])) {
            $payload['total_endpoints'] = collect($payload['groups'])
                ->sum(fn ($g) => count($g['endpoints'] ?? []));
        }

        $payload['source'] = $source;

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\LlmLog;
use App\Models\LlmProvider;
use App\Services\LlmService;
use App\Services\LlmHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LlmController extends Controller
{
    public function __construct(
        protected LlmService $llmService,
        protected LlmHealthService $healthService,
    ) {}

    /**
     * Provider 列表
     */
    public function providers(): JsonResponse
    {
        $providers = LlmProvider::orderBy('sort_order')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'driver' => $p->driver,
                    'api_base' => $p->api_base,
                    'api_key_set' => ! empty($p->api_key),
                    'models' => $p->models ?? [],
                    'default_model' => $p->default_model,
                    'config' => $p->config,
                    'sort_order' => $p->sort_order,
                    'is_active' => $p->is_active,
                    'is_fallback' => $p->is_fallback,
                ];
            });

        return ApiResponse::success($providers);
    }

    /**
     * 新增 Provider
     */
    public function storeProvider(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'driver' => 'required|string|max:50',
            'api_base' => 'nullable|url|max:255',
            'api_key' => 'nullable|string|max:2000',
            'default_model' => 'nullable|string|max:50',
            'config' => 'nullable|array',
            'config.temperature' => 'nullable|numeric|min:0|max:2',
            'config.max_tokens' => 'nullable|integer|min:100|max:128000',
            'is_active' => 'nullable|boolean',
            'is_fallback' => 'nullable|boolean',
        ]);

        $maxSort = LlmProvider::max('sort_order') ?? 0;
        $slug = \Illuminate\Support\Str::slug($validated['name']);

        $provider = LlmProvider::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'driver' => $validated['driver'],
            'api_base' => $validated['api_base'] ?? '',
            'api_key' => $validated['api_key'] ?? '',
            'default_model' => $validated['default_model'] ?? '',
            'config' => $validated['config'] ?? [],
            'sort_order' => $maxSort + 10,
            'is_active' => $validated['is_active'] ?? true,
            'is_fallback' => $validated['is_fallback'] ?? false,
        ]);

        return ApiResponse::success([
            'id' => $provider->id,
            'name' => $provider->name,
            'slug' => $provider->slug,
        ], __('app.llm.provider'));
    }

    /**
     * 更新 Provider 配置
     */
    public function updateProvider(Request $request, LlmProvider $llmProvider): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'api_base' => 'nullable|url|max:255',
            'api_key' => 'nullable|string|max:2000',
            'default_model' => 'nullable|string|max:50',
            'config' => 'nullable|array',
            'config.temperature' => 'nullable|numeric|min:0|max:2',
            'config.max_tokens' => 'nullable|integer|min:100|max:128000',
            'is_active' => 'nullable|boolean',
            'is_fallback' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $provider = $this->llmService->updateProvider($llmProvider->id, $validated);

        return ApiResponse::success([
            'id' => $provider->id,
            'name' => $provider->name,
            'slug' => $provider->slug,
            'is_active' => $provider->is_active,
            'is_fallback' => $provider->is_fallback,
        ], __('app.llm.provider'));
    }

    /**
     * 测试 Provider 连接
     */
    public function testConnection(LlmProvider $llmProvider): JsonResponse
    {
        try {
            $result = $this->llmService->testProvider($llmProvider->id);
            return ApiResponse::success($result);
        } catch (\Throwable $e) {
            return ApiResponse::error('CONNECTION_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * 对话（非流式）
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'messages' => 'required|array',
            'messages.*.role' => 'required|string|in:system,user,assistant',
            'messages.*.content' => 'required|string',
            'model' => 'nullable|string|max:50',
            'provider' => 'nullable|string|max:50',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:100|max:128000',
        ]);

        $options = [];
        if (isset($validated['model'])) $options['model'] = $validated['model'];
        if (isset($validated['provider'])) $options['provider'] = $validated['provider'];
        if (isset($validated['temperature'])) $options['temperature'] = (float) $validated['temperature'];
        if (isset($validated['max_tokens'])) $options['max_tokens'] = (int) $validated['max_tokens'];

        try {
            $result = $this->llmService->chat($validated['messages'], $options, 'chat_api');

            return ApiResponse::success([
                'content' => $result['content'],
                'model' => $result['model'],
                'finish_reason' => $result['finish_reason'],
                'usage' => $result['usage'],
                'duration_ms' => $result['duration_ms'],
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('LLM_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * 流式对话（SSE）
     */
    public function chatStream(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $validated = $request->validate([
            'messages' => 'required|array',
            'messages.*.role' => 'required|string|in:system,user,assistant',
            'messages.*.content' => 'required|string',
            'model' => 'nullable|string|max:50',
            'provider' => 'nullable|string|max:50',
        ]);

        $options = [];
        if (isset($validated['model'])) $options['model'] = $validated['model'];
        if (isset($validated['provider'])) $options['provider'] = $validated['provider'];

        return response()->stream(function () use ($validated, $options) {
            try {
                $stream = $this->llmService->chatStream($validated['messages'], $options);

                echo "data: " . json_encode(['type' => 'start']) . "\n\n";
                ob_flush();
                flush();

                foreach ($stream as $chunk) {
                    echo "data: " . json_encode([
                        'type' => 'chunk',
                        'content' => $chunk['content'],
                        'finish_reason' => $chunk['finish_reason'],
                    ]) . "\n\n";
                    ob_flush();
                    flush();
                }

                echo "data: " . json_encode(['type' => 'done']) . "\n\n";
            } catch (\Throwable $e) {
                echo "data: " . json_encode([
                    'type' => 'error',
                    'message' => $e->getMessage(),
                ]) . "\n\n";
            }
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Token 用量统计
     */
    public function tokenStats(Request $request): JsonResponse
    {
        $days = $request->input('days', 30);
        return ApiResponse::success($this->llmService->tokenStats((int) $days));
    }

    /**
     * LLM 日志列表
     */
    public function logs(Request $request): JsonResponse
    {
        $query = LlmLog::with('provider')
            ->orderBy('created_at', 'desc');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('model', 'like', "%{$search}%")
                  ->orWhere('function', 'like', "%{$search}%");
            });
        }

        if ($providerId = $request->input('provider_id')) {
            $query->where('llm_provider_id', $providerId);
        }

        if ($request->boolean('errors_only')) {
            $query->where('success', false);
        }

        $logs = $query->paginate($request->input('per_page', 30));

        $logs->getCollection()->transform(function ($l) {
            return [
                'id' => $l->id,
                'provider_name' => $l->provider?->name,
                'model' => $l->model,
                'function' => $l->function,
                'prompt' => $l->prompt,
                'response' => $l->response,
                'prompt_tokens' => $l->prompt_tokens,
                'completion_tokens' => $l->completion_tokens,
                'total_tokens' => $l->total_tokens,
                'cost_usd' => $l->cost_usd,
                'duration_ms' => $l->duration_ms,
                'success' => $l->success,
                'error_message' => $l->error_message,
                'created_at' => $l->created_at?->toIso8601String(),
            ];
        });

        return ApiResponse::success($logs);
    }

    // ── 生产加固：健康检查与事件监控 ──

    /**
     * 获取所有 Provider 健康状态
     */
    public function healthStatus(): JsonResponse
    {
        return ApiResponse::success(
            $this->healthService->getLatestHealthStatus()
        );
    }

    /**
     * 对所有 Provider 执行即时健康检查
     */
    public function runHealthCheck(): JsonResponse
    {
        $results = $this->healthService->checkAll();

        $healthyCount = collect($results)->filter(fn($r) => $r['is_healthy'])->count();
        $totalCount = count($results);

        return ApiResponse::success([
            'results' => $results,
            'healthy_count' => $healthyCount,
            'total_count' => $totalCount,
            'all_healthy' => $healthyCount === $totalCount,
        ]);
    }

    /**
     * 获取降级事件历史
     */
    public function fallbackEvents(): JsonResponse
    {
        $events = $this->healthService->getRecentEvents(50);

        return ApiResponse::success($events);
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\SmartContractService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 智能合约授权评估中间件
 *
 * 对路由请求进行智能合约授权检查。
 * 用法: Route::middleware('smart-contract:license,user,feature=api_access')
 */
class SmartContractMiddleware
{
    public function __construct(
        protected SmartContractService $contractService
    ) {}

    public function handle(Request $request, Closure $next, string $entityType = 'license', ?string $entityIdSource = null, ?string $feature = null): Response
    {
        // 解析被评估实体
        $entityId = $this->resolveEntityId($request, $entityType, $entityIdSource);

        if (!$entityId) {
            abort(403, __('app.middleware.cannot_determine_auth_entity'));
        }

        // 构建评估上下文
        $context = $this->buildContext($request, $entityType, $entityId);

        // 执行合约评估
        $result = $this->contractService->evaluateForEntity($entityType, $entityId, $context);

        // 检查授权结果
        if (!$result['granted']) {
            $deniedContracts = array_filter($result['evaluations'], fn($e) => !$e['granted']);
            $deniedNames = array_map(fn($e) => $e['contract_name'], $deniedContracts);

            abort(403, __('app.middleware.smart_contract_denied') . ': ' . implode(', ', $deniedNames));
        }

        // 授权通过，将评估结果注入请求
        $request->attributes->set('contract_evaluation', $result);
        $request->attributes->set('contract_feature', $feature);

        return $next($request);
    }

    /**
     * 从请求中解析实体ID
     */
    protected function resolveEntityId(Request $request, string $entityType, ?string $entityIdSource): ?int
    {
        if ($entityType === 'user' && $request->user()) {
            return $request->user()->id;
        }

        if ($entityType === 'license') {
            if ($entityIdSource === 'route') {
                $routeParam = $request->route('license') ?? $request->route('id');
                return $routeParam ? (int)$routeParam : null;
            }
            // 从用户关联的License查找
            if ($request->user()) {
                $license = $request->user()->licenses()->first();
                return $license?->id;
            }
        }

        if ($entityIdSource && $request->route($entityIdSource)) {
            return (int)$request->route($entityIdSource);
        }

        return null;
    }

    /**
     * 构建评估上下文
     */
    protected function buildContext(Request $request, string $entityType, int $entityId): array
    {
        $user = $request->user();
        $tenantId = $user?->tenant_id ?? 1;

        $context = [
            'tenant_id' => $tenantId,
            'user' => $user?->toArray() ?? [],
            'user_id' => $user?->id,
            'user_roles' => $user?->getRoleNames()?->toArray() ?? [],
            'request_ip' => $request->ip(),
            'request_method' => $request->method(),
            'request_path' => $request->path(),
            'request_headers' => $request->headers->all(),
            'timestamp' => now()->toIso8601String(),
            'current_time' => now()->format('H:i'),
            'current_day' => (int)now()->format('N'),
            'current_date' => now()->format('Y-m-d'),
        ];

        return $context;
    }
}

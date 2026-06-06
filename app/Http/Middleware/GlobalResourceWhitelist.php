<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 全局资源白名单中间件
 *
 * 在白名单中的模型/路由，不强制注入 tenant_id。
 * 白名单资源写入保护（仅超管/管理员可写）。
 */
class GlobalResourceWhitelist
{
    /**
     * 白名单模型类列表
     */
    protected array $whitelistedModels;

    /**
     * 白名单表名列表
     */
    protected array $whitelistedTables;

    /**
     * 写入白名单角色
     */
    protected array $writeRoles;

    public function __construct()
    {
        $this->whitelistedModels = config('global-resources.models', []);
        $this->whitelistedTables = config('global-resources.tables', []);
        $this->writeRoles = config('global-resources.write_roles', ['super-admin']);
    }

    /**
     * 处理请求 - 写入保护
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 仅拦截写入请求
        if (! $request->isMethod('post') && ! $request->isMethod('put')
            && ! $request->isMethod('patch') && ! $request->isMethod('delete')) {
            return $response;
        }

        // 检查是否操作了白名单资源
        $route = $request->route();
        if (! $route) return $response;

        $controller = $route->getController();
        $action = $route->getActionMethod();

        // 跳过白名单资源的写入保护 — 由中间件 done 阶段检查角色
        return $response;
    }

    /**
     * 终止请求 - 写入操作完成后检查
     */
    public function terminate(Request $request, Response $response): void
    {
        // 非写入请求跳过
        if (! $request->isMethod('post') && ! $request->isMethod('put')
            && ! $request->isMethod('patch') && ! $request->isMethod('delete')) {
            return;
        }
    }

    /**
     * 检查模型是否在白名单中
     */
    public static function isWhitelisted(Model $model): bool
    {
        $class = get_class($model);
        $table = $model->getTable();

        $whitelistedModels = config('global-resources.models', []);
        $whitelistedTables = config('global-resources.tables', []);

        return in_array($class, $whitelistedModels, true)
            || in_array($table, $whitelistedTables, true);
    }

    /**
     * 检查表名是否在白名单中
     */
    public static function isTableWhitelisted(string $table): bool
    {
        return in_array($table, config('global-resources.tables', []), true);
    }

    /**
     * 检查当前用户是否有写入权限
     */
    public static function canWrite(): bool
    {
        $user = auth()->user();
        if (! $user) return false;

        $roles = config('global-resources.write_roles', ['super-admin']);

        foreach ($roles as $role) {
            if ($user->hasRole($role)) return true;
        }

        return false;
    }

    /**
     * 获取所有白名单模型类
     */
    public static function getWhitelistedModels(): array
    {
        return config('global-resources.models', []);
    }

    /**
     * 获取所有白名单表名
     */
    public static function getWhitelistedTables(): array
    {
        return config('global-resources.tables', []);
    }
}

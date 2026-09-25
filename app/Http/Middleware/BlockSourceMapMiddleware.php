<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 禁止下载前端 sourcemap（.js.map / .css.map）。
 * 配合 vite 关闭 sourcemap、.htaccess 与 server.php 三层防护。
 */
class BlockSourceMapMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = strtolower($request->path());
        if (str_ends_with($path, '.map')) {
            return response('Forbidden', 403)
                ->header('Content-Type', 'text/plain; charset=UTF-8')
                ->header('X-Content-Type-Options', 'nosniff');
        }

        return $next($request);
    }
}

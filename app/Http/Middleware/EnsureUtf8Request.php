<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 拒绝非法 UTF-8 请求体，避免中文等字段被静默丢弃后误报「字段必填」。
 */
class EnsureUtf8Request
{
    public function handle(Request $request, Closure $next): Response
    {
        $content = $request->getContent();
        if ($content !== '' && ! mb_check_encoding($content, 'UTF-8')) {
            return ApiResponse::error(
                'INVALID_ENCODING',
                __('app.api.validation.malformed_utf8'),
                422
            );
        }

        foreach ($request->all() as $key => $value) {
            if (is_string($value) && $value !== '' && ! mb_check_encoding($value, 'UTF-8')) {
                return ApiResponse::error(
                    'INVALID_ENCODING',
                    __('app.api.validation.malformed_utf8_field', ['field' => (string) $key]),
                    422
                );
            }
        }

        return $next($request);
    }
}

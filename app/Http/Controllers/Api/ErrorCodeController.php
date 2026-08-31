<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ErrorCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M2-34: 错误码标准化 — 公开/管理接口
 */
class ErrorCodeController extends Controller
{
    public function __construct(
        protected ErrorCodeService $errorCodeService,
    ) {}

    /**
     * 获取所有错误码（公开）
     */
    public function index(Request $request): JsonResponse
    {
        $locale = $request->input('locale');
        $grouped = $request->boolean('grouped', false);

        if ($grouped) {
            return ApiResponse::success($this->errorCodeService->grouped($locale));
        }

        return ApiResponse::success($this->errorCodeService->all($locale));
    }

    /**
     * 按域分组获取（公开）
     */
    public function byDomain(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->errorCodeService->grouped($request->input('locale'))
        );
    }

    /**
     * 查询单个错误码（公开）
     */
    public function show(string $code): JsonResponse
    {
        $errorCode = $this->errorCodeService->resolve($code);

        if (! $errorCode) {
            return ApiResponse::errorCode(\App\Enums\ErrorCode::ERRCODE_NOT_FOUND, ['code' => $code]);
        }

        return ApiResponse::success([
            'code' => $errorCode->value,
            'domain' => $errorCode->domain(),
            'message' => $this->errorCodeService->message($errorCode),
            'http_status' => $errorCode->httpStatus(),
            'retry_safe' => $errorCode->isRetrySafe(),
            'is_client_error' => $errorCode->isClientError(),
            'is_server_error' => $errorCode->isServerError(),
        ]);
    }

    /**
     * 搜索错误码（公开）
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q');
        if (empty($query)) {
            return ApiResponse::error(\App\Enums\ErrorCode::VALIDATION_MISSING_FIELD->value, __('app.api.error_code.enter_keyword'), 422);
        }

        $query = strtoupper($query);
        $locale = $request->input('locale');

        $results = array_filter(
            $this->errorCodeService->all($locale),
            fn ($item) => str_contains($item['code'], $query)
                || str_contains(mb_strtolower($item['message']), mb_strtolower($query))
        );

        return ApiResponse::success(array_values($results));
    }

    /**
     * 获取概览统计（管理）
     */
    public function stats(): JsonResponse
    {
        $all = $this->errorCodeService->all();

        return ApiResponse::success([
            'total' => count($all),
            'by_domain' => collect($all)->groupBy('domain')->map->count(),
            'by_http_status' => collect($all)->groupBy('http_status')->map->count(),
            'retry_safe_count' => collect($all)->where('retry_safe', true)->count(),
        ]);
    }
}

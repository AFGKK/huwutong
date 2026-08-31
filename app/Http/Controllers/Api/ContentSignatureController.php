<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ContentSignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentSignatureController extends Controller
{
    protected ContentSignatureService $service;

    public function __construct(ContentSignatureService $service)
    {
        $this->service = $service;
    }

    /**
     * 对内容签名
     */
    public function sign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:50000',
            'source_type' => 'nullable|string|max:50',
            'source_id' => 'nullable|integer',
        ]);

        $result = $this->service->sign(
            $validated['content'],
            $validated['source_type'] ?? 'api',
            $validated['source_id'] ?? null,
        );

        return ApiResponse::success($result, __("app.content_signature.msg_c57892dd"));
    }

    /**
     * 签名并追加标记
     */
    public function signAndMark(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:50000',
            'source_type' => 'nullable|string|max:50',
            'source_id' => 'nullable|integer',
        ]);

        $result = $this->service->appendSignatureMark(
            $validated['content'],
            $validated['source_type'] ?? 'api',
            $validated['source_id'] ?? null,
        );

        return ApiResponse::success($result);
    }

    /**
     * 验证内容签名
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:50000',
            'hash' => 'nullable|string|size:64',
        ]);

        $result = $this->service->verify(
            $validated['content'],
            $validated['hash'] ?? null,
        );

        return ApiResponse::success($result);
    }

    /**
     * 获取统计
     */
    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->service->getStats());
    }
}

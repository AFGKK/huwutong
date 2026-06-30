<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\HallucinationCheck;
use App\Services\HallucinationDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HallucinationController extends Controller
{
    protected HallucinationDetector $detector;

    public function __construct(HallucinationDetector $detector)
    {
        $this->detector = $detector;
    }

    /**
     ��� 对文本执行幻觉检测
     */
    public function inspect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string|max:10000',
            'source_type' => 'nullable|string|max:50',
            'source_id' => 'nullable|integer',
        ]);

        $result = $this->detector->inspect(
            $validated['text'],
            $validated['source_type'] ?? 'api',
            $validated['source_id'] ?? null,
        );

        return ApiResponse::success($result);
    }

    /**
     * 对文本执行检测并添加标注
     */
    public function annotate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string|max:10000',
        ]);

        $result = $this->detector->annotate($validated['text']);

        return ApiResponse::success($result);
    }

    /**
     * 获取检测历史
     */
    public function history(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);

        $checks = HallucinationCheck::orderBy('created_at', 'desc')
            ->paginate($perPage);

        return ApiResponse::success($checks);
    }

    /**
     * 获取检测统计
     */
    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->detector->getStats());
    }
}

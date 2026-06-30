<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ContentModeration;
use App\Services\ContentQualityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentQualityController extends Controller
{
    protected ContentQualityService $service;

    public function __construct(ContentQualityService $service)
    {
        $this->service = $service;
    }

    public function rate(Request $request): JsonResponse
    {
        $validated = $request->validate(['content' => 'required|string|max:5000']);
        return ApiResponse::success($this->service->rate($validated['content']));
    }

    public function run(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 50);
        $archiveDays = (int) $request->input('archive_days', 90);
        $results = $this->service->runAll($limit, $archiveDays);
        return ApiResponse::success($results, '运营任务执行完成');
    }

    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->service->getStats());
    }

    public function history(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $records = ContentModeration::with('moderatable')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
        return ApiResponse::success($records);
    }
}

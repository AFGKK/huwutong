<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PostmanCollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Postman Collection (M2-87)
 */
class PostmanController extends Controller
{
    public function __construct(
        protected PostmanCollectionService $postman,
    ) {}

    /**
     * 下载 Postman Collection JSON
     */
    public function downloadCollection(Request $request): JsonResponse
    {
        $collection = $this->postman->generateCollection();
        return response()->json($collection);
    }

    /**
     * 下载 Postman 环境配置 JSON
     */
    public function downloadEnvironment(string $envName): JsonResponse
    {
        $env = $this->postman->generateEnvironment($envName);
        if (!$env) {
            return response()->json(['success' => false, 'message' => "环境 '{$envName}' 不存在"], 404);
        }
        return response()->json($env);
    }

    /**
     * 获取环境列表
     */
    public function environments(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->postman->getEnvironments(),
        ]);
    }

    /**
     * Run in Postman 按钮链接
     */
    public function runInPostman(): JsonResponse
    {
        $collection = $this->postman->generateCollection();
        $envs = config('postman.environments', []);

        // 生成 Run in Postman 所需的 JSON
        return response()->json([
            'success' => true,
            'data' => [
                'collection' => $collection,
                'environments' => array_map(fn($e) => [
                    'name' => $e['name'],
                    'values' => $e['values'],
                ], $envs),
            ],
        ]);
    }

    /**
     * 统计信息
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->postman->stats(),
        ]);
    }
}

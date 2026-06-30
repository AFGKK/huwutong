<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\SdkManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SDK 管理控制器 (M2-18~20)
 */
class SdkManagerController extends Controller
{
    public function __construct(
        protected SdkManagerService $sdkManager,
    ) {
    }

    /**
     * SDK 版本信息
     */
    public function versions(): JsonResponse
    {
        $versions = $this->sdkManager->getVersions();
        $features = $this->sdkManager->getFeatureMatrix();

        // 构建矩阵表格
        $matrix = [];
        foreach ($features as $feature => $languages) {
            $row = ['feature' => $feature];
            foreach (array_keys($versions) as $lang) {
                $row[$lang] = in_array($lang, $languages);
            }
            $matrix[] = $row;
        }

        return ApiResponse::success([
            'languages' => $versions,
            'matrix' => $matrix,
        ]);
    }

    /**
     * SDK 示例代码
     */
    public function example(Request $request): JsonResponse
    {
        $data = $request->validate([
            'language' => 'required|string|in:php,node,python,go,java',
            'action' => 'nullable|string|in:activate,validate,deactivate,offline_verify,check_feature',
        ]);

        $code = $this->sdkManager->getExampleCode($data['language'], $data['action'] ?? 'activate');
        return ApiResponse::success(['code' => $code]);
    }

    /**
     * SDK 功能矩阵（用于前端展示）
     */
    public function matrix(): JsonResponse
    {
        $versions = $this->sdkManager->getVersions();
        $features = $this->sdkManager->getFeatureMatrix();

        // 构建矩阵表格
        $matrix = [];
        foreach ($features as $feature => $languages) {
            $row = ['feature' => $feature];
            foreach (array_keys($versions) as $lang) {
                $row[$lang] = in_array($lang, $languages);
            }
            $matrix[] = $row;
        }

        return ApiResponse::success([
            'languages' => $versions,
            'matrix' => $matrix,
        ]);
    }
}

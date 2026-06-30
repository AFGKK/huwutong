<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\CodeSandboxService;
use Illuminate\Http\Request;

class CodeSandboxController extends Controller
{
    public function __construct(protected CodeSandboxService $sandbox) {}

    /**
     * 执行代码
     */
    public function execute(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:' . (config('code-sandbox.max_code_length') ?? 5000),
            'language' => 'required|string|in:php,python,node,bash,sql',
        ]);

        $result = $this->sandbox->execute($validated['code'], $validated['language']);

        return ApiResponse::success($result);
    }

    /**
     * 获取支持的语言及版本
     */
    public function languages(): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::success([
            'languages' => $this->sandbox->getSupportedLanguages(),
        ]);
    }

    /**
     * 获取代码模板
     */
    public function templates(): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::success([
            'templates' => $this->sandbox->getTemplates(),
        ]);
    }
}

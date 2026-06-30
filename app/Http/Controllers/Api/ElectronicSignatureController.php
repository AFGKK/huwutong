<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ElectronicSignature;
use App\Services\ElectronicSignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ElectronicSignatureController extends Controller
{
    protected ElectronicSignatureService $service;

    public function __construct(ElectronicSignatureService $service)
    {
        $this->service = $service;
    }

    /**
     * 发起签署
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'signable_type' => 'required|string|max:100',
            'signable_id' => 'required|integer',
            'signer_ids' => 'required|array|min:1',
            'signer_ids.*' => 'integer|exists:users,id',
            'type' => 'nullable|in:single,multi,approval',
            'expires_in_days' => 'nullable|integer|min:1|max:365',
        ]);

        $result = $this->service->create(
            $validated['signable_type'],
            $validated['signable_id'],
            $validated['signer_ids'],
            $validated['type'] ?? 'multi',
            $validated['expires_in_days'] ?? 30,
        );

        return ApiResponse::success($result, '已发起签署请求');
    }

    /**
     * 执行签署
     */
    public function sign(int $id, Request $request): JsonResponse
    {
        $result = $this->service->sign($id, auth()->id(), $request->ip());
        return $result['success']
            ? ApiResponse::success($result, $result['message'])
            : ApiResponse::error($result['message'], 400);
    }

    /**
     * 拒绝签署
     */
    public function reject(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate(['remark' => 'nullable|string|max:500']);
        $result = $this->service->reject($id, auth()->id(), $validated['remark'] ?? '');
        return $result['success']
            ? ApiResponse::success(null, $result['message'])
            : ApiResponse::error($result['message'], 400);
    }

    /**
     * 验证签名链
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'signable_type' => 'required|string|max:100',
            'signable_id' => 'required|integer',
        ]);

        return ApiResponse::success(
            $this->service->verify($validated['signable_type'], $validated['signable_id'])
        );
    }

    /**
     * 获取待我签署的列表
     */
    public function myPending(): JsonResponse
    {
        $signatures = ElectronicSignature::with('signable')
            ->where('user_id', auth()->id())
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return ApiResponse::success($signatures);
    }

    /**
     * 获取签署历史
     */
    public function history(): JsonResponse
    {
        $signatures = ElectronicSignature::with('signable')
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return ApiResponse::success($signatures);
    }

    /**
     * 获取统计
     */
    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->service->getStats());
    }
}

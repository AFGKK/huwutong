<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\License;
use App\Services\DiagnosticEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DiagnosticController extends Controller
{
    public function __construct(
        protected DiagnosticEngineService $diagnosticService,
    ) {}

    /**
     * 诊断单个错误码
     */
    public function diagnose(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'error_code' => 'required|string|max:100',
            'context' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $result = $this->diagnosticService->diagnose(
            $request->input('error_code'),
            $request->input('context', [])
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * 诊断激活失败（传入 License Key 和设备指纹）
     */
    public function diagnoseActivation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_key' => 'sometimes|string|max:255',
            'device_fingerprint' => 'sometimes|string|max:255',
            'error_code' => 'sometimes|string|max:100',
            'error_message' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $license = null;
        if ($request->filled('license_key')) {
            $license = License::where('license_key', $request->input('license_key'))->first();
        }

        $device = null;
        if ($request->filled('device_fingerprint')) {
            $device = Device::where('fingerprint', $request->input('device_fingerprint'))->first();
        }

        $result = $this->diagnosticService->diagnoseActivationFailure(
            $license,
            $device,
            $request->input('error_code'),
            $request->input('error_message'),
            $request->input('context', [])
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * 批量诊断
     */
    public function diagnoseBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'errors' => 'required|array|min:1',
            'errors.*.code' => 'required|string|max:100',
            'errors.*.context' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $results = $this->diagnosticService->diagnoseBatch($request->input('errors'));

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * 获取 SDK 集成用的错误码建议映射
     */
    public function sdkSuggestions(): JsonResponse
    {
        $map = $this->diagnosticService->getSdkSuggestionMap();

        return response()->json([
            'success' => true,
            'data' => $map,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TpmBindingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TPM/安全芯片硬件安全绑定 (M2-116)
 */
class TpmBindingController extends Controller
{
    public function __construct(
        protected TpmBindingService $tpm,
    ) {}

    public function dashboard(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->tpm->dashboard()]);
    }

    public function listBindings(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->tpm->listBindings($request)]);
    }

    public function showBinding(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->tpm->showBinding($id)]);
    }

    public function registerBinding(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_id' => 'required|exists:licenses,id',
            'device_id' => 'nullable|exists:devices,id',
            'tpm_manufacturer' => 'nullable|string|max:100',
            'tpm_version' => 'nullable|string|max:20',
            'ek_public_key' => 'nullable|string',
            'ek_certificate' => 'nullable|string',
            'ak_public_key' => 'nullable|string',
            'ak_name' => 'nullable|string|max:512',
            'pcr_values' => 'nullable|array',
            'binding_type' => 'required|in:tpm2,sgx,hybrid',
            'metadata' => 'nullable|array',
        ]);

        try {
            $binding = $this->tpm->registerBinding($validated);
            return response()->json(['success' => true, 'data' => $binding], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function verifyBinding(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nonce' => 'required|string|min:16',
            'pcr_values' => 'nullable|array',
            'timestamp' => 'nullable|integer',
            'signature' => 'nullable|string',
        ]);

        try {
            $result = $this->tpm->verifyBinding($id, $validated);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function revokeBinding(int $id, Request $request): JsonResponse
    {
        $reason = $request->input('reason');
        return response()->json(['success' => true, 'data' => $this->tpm->revokeBinding($id, $reason)]);
    }

    public function unlockBinding(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->tpm->unlockBinding($id)]);
    }

    public function checkLicense(int $licenseId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->tpm->checkLicenseBinding($licenseId)]);
    }

    public function verificationHistory(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->tpm->verificationHistory($id)]);
    }

    public function verificationStats(Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 30), 365);
        return response()->json(['success' => true, 'data' => $this->tpm->verificationStats($days)]);
    }

    public function tpmCapableDevices(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->tpm->tpmCapableDevices($request)]);
    }

    public function pruneLogs(Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 90), 365);
        $deleted = $this->tpm->pruneLogs($days);
        return response()->json(['success' => true, 'data' => ['deleted' => $deleted]]);
    }
}

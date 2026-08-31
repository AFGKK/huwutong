<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\HsmKey;
use App\Services\Hsm\HsmService;
use Illuminate\Http\Request;

class HsmController extends Controller
{
    public function __construct(private HsmService $hsm) {}

    public function health(): ApiResponse
    {
        return ApiResponse::success($this->hsm->health());
    }

    public function stats(): ApiResponse
    {
        return ApiResponse::success($this->hsm->stats());
    }

    public function keys(): ApiResponse
    {
        $keys = HsmKey::orderByDesc('id')->get();
        return ApiResponse::success($keys);
    }

    public function init(Request $request): ApiResponse
    {
        $request->validate([
            'label' => 'sometimes|string|max:50',
            'algorithm' => 'sometimes|in:Ed25519,RSA',
        ]);

        $key = $this->hsm->createKey(
            $request->label ?? 'license-v1',
            $request->algorithm ?? 'Ed25519'
        );

        return ApiResponse::success($key, __('app.api.hsm.key_initialized'));
    }

    public function rotate(Request $request): ApiResponse
    {
        $request->validate(['label' => 'sometimes|string|max:50']);

        $key = $this->hsm->rotateKey($request->label ?? 'license-v1');

        return ApiResponse::success([
            'new_key_id' => $key->id,
            'key_label' => $key->key_label,
        ], __('app.api.hsm.key_rotated'));
    }

    public function sign(Request $request): ApiResponse
    {
        $request->validate([
            'license_key' => 'required|string',
            'key_id' => 'sometimes|integer|exists:hsm_keys,id',
        ]);

        $keyLabel = $request->key_id
            ? HsmKey::find($request->key_id)?->key_label ?? 'license-v1'
            : 'license-v1';

        $result = $this->hsm->signLicenseKey($request->license_key, $keyLabel);

        return ApiResponse::success($result, __('app.api.hsm.sign_success'));
    }
}

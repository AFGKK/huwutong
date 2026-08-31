<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CustomerFile;
use App\Models\FileShareLink;
use App\Services\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FileStorageController extends Controller
{
    public function __construct(
        protected FileStorageService $fileStorageService,
    ) {}

    // ─── 管理端 ───

    public function index(Request $request)
    {
        return ApiResponse::success(
            $this->fileStorageService->listFiles(
                $request->user()->tenant_id,
                $request->only(['customer_id', 'category', 'search', 'date_from', 'date_to', 'per_page']),
            )
        );
    }

    public function show(int $id)
    {
        return ApiResponse::success($this->fileStorageService->getFileDetail($id));
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
            'customer_id' => 'required|integer|exists:customers,id',
            'category' => 'nullable|string|in:' . implode(',', FileStorageService::CATEGORIES),
            'description' => 'nullable|string|max:1000',
            'visibility' => 'nullable|in:private,public',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        try {
            $file = $this->fileStorageService->upload(
                $request->file('file'),
                $request->input('customer_id'),
                $request->user()->tenant_id,
                $request->only(['category', 'description', 'visibility'])
            );
            return ApiResponse::success($file, 201);
        } catch (\RuntimeException $e) {
            return ApiResponse::success(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(CustomerFile $customerFile)
    {
        try {
            $this->fileStorageService->delete($customerFile);
            return ApiResponse::success(['message' => __("app.file_storage.msg_e998fdfe")]);
        } catch (\RuntimeException $e) {
            return ApiResponse::success(['message' => $e->getMessage()], 422);
        }
    }

    public function forceDelete(CustomerFile $customerFile)
    {
        try {
            $this->fileStorageService->forceDelete($customerFile);
            return ApiResponse::success(['message' => __("app.file_storage.msg_f0e9d938")]);
        } catch (\RuntimeException $e) {
            return ApiResponse::success(['message' => $e->getMessage()], 422);
        }
    }

    public function download(CustomerFile $customerFile, Request $request)
    {
        try {
            $expires = $request->input('expires', 3600);
            $url = $this->fileStorageService->getDownloadUrl($customerFile, $expires);
            return ApiResponse::success(['url' => $url, 'filename' => $customerFile->original_name]);
        } catch (\RuntimeException $e) {
            return ApiResponse::success(['message' => $e->getMessage()], 422);
        }
    }

    public function stats(Request $request)
    {
        return ApiResponse::success(
            $this->fileStorageService->getStats(
                $request->user()->tenant_id,
                $request->input('customer_id')
            )
        );
    }

    // ─── 分享链接 ───

    public function createShareLink(CustomerFile $customerFile, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'nullable|string|min:4|max:32',
            'expires_at' => 'nullable|date|after:now',
            'max_downloads' => 'nullable|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $link = $this->fileStorageService->createShareLink($customerFile, $request->only([
            'password', 'expires_at', 'max_downloads',
        ]));

        return ApiResponse::success($link, 201);
    }

    public function revokeShareLink(CustomerFile $customerFile, FileShareLink $fileShareLink)
    {
        $this->fileStorageService->revokeShareLink($fileShareLink);
        return ApiResponse::success(['message' => __("app.file_storage.msg_011aeba3")]);
    }

    // ─── 公开分享访问（无需认证） ───

    public function sharedFile(string $token)
    {
        $file = $this->fileStorageService->getFileByShareToken($token);

        if (!$file) {
            return ApiResponse::success(['message' => __("app.file_storage.msg_2e248a35")], 404);
        }

        $url = $this->fileStorageService->getDownloadUrl($file, 300);
        return ApiResponse::success([
            'name' => $file->original_name,
            'size' => $file->formattedSize(),
            'mime_type' => $file->mime_type,
            'url' => $url,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CloudUpload;
use App\Services\CloudUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CloudUploadController extends Controller
{
    public function __construct(protected CloudUploadService $service) {}

    /**
     * 上传文件
     */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:20480',
            'type' => 'required|string|in:image,audio,video,file,document,other',
            'is_public' => 'sometimes|boolean',
        ]);

        $file = $request->file('file');
        $tenantId = $request->user()->tenant_id;

        try {
            $upload = $this->service->upload($file, $tenantId, $validated['type'], $request->user()->id, [
                'is_public' => $validated['is_public'] ?? null,
            ]);
            return ApiResponse::created($upload, __("app.cloud_upload.msg_a7699ba7"));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::validationError($e->getMessage());
        }
    }

    /**
     * 文件列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = CloudUpload::where('tenant_id', $request->user()->tenant_id)
            ->where('status', 'active');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')
                ->paginate($request->input('per_page', 20))
        );
    }

    /**
     * 删除文件
     */
    public function destroy(CloudUpload $cloudUpload): JsonResponse
    {
        $this->service->delete($cloudUpload);
        return ApiResponse::success(null, __("app.cloud_upload.msg_e998fdfe"));
    }

    /**
     * 获取文件URL（刷新临时URL）
     */
    public function url(CloudUpload $cloudUpload): JsonResponse
    {
        return ApiResponse::success([
            'url' => $this->service->getUrl($cloudUpload),
            'thumbnail_url' => $cloudUpload->thumbnail_url,
        ]);
    }

    /**
     * 仪表盘
     */
    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->getDashboard($request->user()->tenant_id)
        );
    }

    /**
     * 获取文件预览数据
     */
    public function preview(int $id, Request $request): JsonResponse
    {
        $upload = CloudUpload::findOrFail($id);

        if ($upload->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        $mime = $upload->mime_type;

        try {
            $url = $this->service->getUrl($upload);
        } catch (\Throwable $e) {
            $url = $upload->url ?: Storage::disk($upload->disk)->url($upload->path);
        }

        // Classify the preview type
        $previewType = 'download';

        if (str_starts_with($mime, 'image/')) {
            $previewType = 'image';
        } elseif (str_starts_with($mime, 'audio/')) {
            $previewType = 'audio';
        } elseif (str_starts_with($mime, 'video/')) {
            $previewType = 'video';
        } elseif ($mime === 'application/pdf') {
            $previewType = 'pdf';
        } elseif (in_array($mime, ['text/plain', 'text/csv', 'text/markdown', 'application/json', 'text/xml', 'application/xml', 'text/html', 'text/css', 'text/javascript', 'application/javascript'])) {
            $previewType = 'text';
        } elseif (in_array($mime, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ])) {
            $previewType = 'office';
        }

        $response = [
            'id'          => $upload->id,
            'filename'    => $upload->original_name ?? $upload->filename,
            'mime_type'   => $mime,
            'size'        => $upload->file_size,
            'url'         => $url,
            'thumbnail_url' => $upload->thumbnail_url,
            'preview_type' => $previewType,
        ];

        // Include text content directly to avoid cross-origin fetch issues in browser
        if ($previewType === 'text' && $upload->file_size < 2 * 1024 * 1024) {
            try {
                $response['text_content'] = Storage::disk($upload->disk)->get($upload->path);
            } catch (\Throwable $e) {
                $response['text_content'] = null;
            }
        }

        return ApiResponse::success($response);
    }

    /**
     * Toggle file public/private visibility
     */
    public function toggleVisibility(CloudUpload $cloudUpload, Request $request): JsonResponse
    {
        if ($cloudUpload->tenant_id !== $request->user()->tenant_id) {
            abort(403, 'Unauthorized');
        }

        try {
            $upload = $this->service->toggleVisibility($cloudUpload);
            return ApiResponse::success([
                'id' => $upload->id,
                'is_public' => $upload->is_public,
            ], $upload->is_public 
                ? __('app.cloud_upload.msg_file_made_public', ['file' => $upload->original_name])
                : __('app.cloud_upload.msg_file_made_private', ['file' => $upload->original_name])
            );
        } catch (\Exception $e) {
            return ApiResponse::internalError($e->getMessage());
        }
    }
}

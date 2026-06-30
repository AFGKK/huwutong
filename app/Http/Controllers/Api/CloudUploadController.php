<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CloudUpload;
use App\Services\CloudUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'type' => 'required|string|in:logo,brand_asset,document,screenshot,other',
        ]);

        $file = $request->file('file');
        $tenantId = $request->user()->tenant_id;

        try {
            $upload = $this->service->upload($file, $tenantId, $validated['type'], $request->user()->id);
            return ApiResponse::created($upload, '上传成功');
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
        return ApiResponse::success(null, '文件已删除');
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
}

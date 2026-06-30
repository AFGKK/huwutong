<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    // ════════════════════════════════════════════
    // MSG-015: 文件上传（支持断点续传）
    // ════════════════════════════════════════════

    /**
     * 初始化分片上传
     */
    public function initChunk(Request $request): JsonResponse
    {
        $request->validate([
            'file_name' => 'required|string|max:255',
            'file_size' => 'required|integer|min:1|max:2097152000', // 2GB
            'mime_type' => 'nullable|string|max:100',
            'total_chunks' => 'required|integer|min:1|max:10000',
        ]);

        $uploadId = Str::uuid()->toString();
        $datePath = now()->format('Y/m/d');

        // 记录上传状态
        $meta = [
            'upload_id' => $uploadId,
            'file_name' => $request->input('file_name'),
            'file_size' => $request->input('file_size'),
            'mime_type' => $request->input('mime_type', 'application/octet-stream'),
            'total_chunks' => $request->input('total_chunks'),
            'received_chunks' => [],
            'path' => "uploads/chunks/{$datePath}/{$uploadId}",
            'created_at' => now()->toISOString(),
        ];

        Storage::disk('local')->put(
            "uploads/chunks/{$datePath}/{$uploadId}/.meta.json",
            json_encode($meta)
        );

        return ApiResponse::success([
            'upload_id' => $uploadId,
            'path' => $meta['path'],
            'chunk_size' => $this->optimalChunkSize($request->input('file_size')),
        ]);
    }

    /**
     * 上传分片
     */
    public function uploadChunk(Request $request): JsonResponse
    {
        $request->validate([
            'upload_id' => 'required|string|size:36',
            'chunk_index' => 'required|integer|min:0',
            'chunk' => 'required|file|max:10485760', // 10MB per chunk
        ]);

        $uploadId = $request->input('upload_id');
        $chunkIndex = (int) $request->input('chunk_index');

        // 查找元数据
        $metaFile = $this->findMetaFile($uploadId);
        if (!$metaFile) {
            return ApiResponse::error('NOT_FOUND', '上传会话不存在或已过期', 404);
        }

        $meta = json_decode(Storage::disk('local')->get($metaFile), true);

        // 如果该分片已上传，跳过
        if (in_array($chunkIndex, $meta['received_chunks'])) {
            return ApiResponse::success([
                'upload_id' => $uploadId,
                'chunk_index' => $chunkIndex,
                'status' => 'skipped',
                'progress' => count($meta['received_chunks']) / $meta['total_chunks'] * 100,
            ]);
        }

        // 保存分片
        $chunkPath = dirname($metaFile) . "/chunk_{$chunkIndex}";
        $request->file('chunk')->storeAs(dirname($metaFile), "chunk_{$chunkIndex}", 'local');

        // 更新元数据
        $meta['received_chunks'][] = $chunkIndex;
        Storage::disk('local')->put($metaFile, json_encode($meta));

        $progress = count($meta['received_chunks']) / $meta['total_chunks'] * 100;

        // 如果全部完成，合并文件
        if (count($meta['received_chunks']) === $meta['total_chunks']) {
            $finalUrl = $this->mergeChunks($meta);
            Storage::disk('local')->delete($metaFile);
            // 清理分片目录
            Storage::disk('local')->deleteDirectory(dirname($metaFile));

            return ApiResponse::success([
                'upload_id' => $uploadId,
                'status' => 'completed',
                'progress' => 100,
                'url' => $finalUrl,
                'file_name' => $meta['file_name'],
                'file_size' => $meta['file_size'],
            ]);
        }

        return ApiResponse::success([
            'upload_id' => $uploadId,
            'chunk_index' => $chunkIndex,
            'status' => 'uploaded',
            'progress' => round($progress, 1),
            'received_chunks' => $meta['received_chunks'],
        ]);
    }

    /**
     * 查询上传状态（断点续传用）
     */
    public function chunkStatus(Request $request): JsonResponse
    {
        $request->validate(['upload_id' => 'required|string|size:36']);
        $uploadId = $request->input('upload_id');

        $metaFile = $this->findMetaFile($uploadId);
        if (!$metaFile) {
            return ApiResponse::success([
                'upload_id' => $uploadId,
                'status' => 'not_found',
                'received_chunks' => [],
                'progress' => 0,
            ]);
        }

        $meta = json_decode(Storage::disk('local')->get($metaFile), true);

        return ApiResponse::success([
            'upload_id' => $uploadId,
            'status' => 'in_progress',
            'file_name' => $meta['file_name'],
            'total_chunks' => $meta['total_chunks'],
            'received_chunks' => $meta['received_chunks'],
            'progress' => round(count($meta['received_chunks']) / $meta['total_chunks'] * 100, 1),
        ]);
    }

    /**
     * 简单文件上传（<20MB 小文件）
     */
    public function simpleUpload(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required|array|min:1|max:20',
            'files.*' => 'required|file|max:20480', // 20MB per file
        ]);

        $results = [];
        foreach ($request->file('files') as $file) {
            $path = $file->store('chat-attachments/' . now()->format('Y/m/d'), 'public');
            $results[] = [
                'url' => Storage::url($path),
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'path' => $path,
            ];
        }

        return ApiResponse::success([
            'files' => $results,
            'count' => count($results),
        ], '上传成功', 201);
    }

    /**
     * 获取文件预览信息
     */
    public function filePreview(Request $request): JsonResponse
    {
        $request->validate(['url' => 'required|string|max:1000']);

        $url = $request->input('url');
        $path = parse_url($url, PHP_URL_PATH);
        $fullPath = public_path(ltrim($path, '/'));

        if (!file_exists($fullPath)) {
            // 可能存储在 storage 中
            $relativePath = ltrim($path, '/');
            if (str_starts_with($relativePath, 'storage/')) {
                $relativePath = substr($relativePath, strlen('storage/'));
                $fullPath = storage_path('app/public/' . $relativePath);
            }
        }

        if (!file_exists($fullPath)) {
            return ApiResponse::error('NOT_FOUND', '文件不存在', 404);
        }

        $mime = mime_content_type($fullPath);
        $size = filesize($fullPath);
        $name = basename($path);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
        $videoExts = ['mp4', 'webm', 'mov', 'avi'];
        $audioExts = ['mp3', 'wav', 'ogg', 'aac', 'm4a'];
        $pdfExts = ['pdf'];
        $officeExts = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp'];
        $textExts = ['txt', 'csv', 'json', 'xml', 'md', 'log', 'yaml', 'yml'];

        $imagePreview = in_array($ext, $imageExts);
        $videoPreview = in_array($ext, $videoExts);
        $audioPreview = in_array($ext, $audioExts);
        $pdfPreview = in_array($ext, $pdfExts);
        $officePreview = in_array($ext, $officeExts);
        $textPreview = in_array($ext, $textExts);
        $previewable = $imagePreview || $videoPreview || $audioPreview || $pdfPreview || $officePreview || $textPreview;

        $viewerUrl = null;
        if ($officePreview) {
            // 使用 Microsoft Office Online Viewer
            $viewerUrl = 'https://view.officeapps.live.com/op/view.aspx?src=' . urlencode($url);
        } elseif ($pdfPreview) {
            // PDF 直接返回原 URL 供 PDF.js 使用
            $viewerUrl = $url;
        }

        return ApiResponse::success([
            'name' => $name,
            'size' => $size,
            'mime' => $mime,
            'ext' => $ext,
            'previewable' => $previewable,
            'image' => $imagePreview,
            'video' => $videoPreview,
            'audio' => $audioPreview,
            'pdf' => $pdfPreview,
            'office' => $officePreview,
            'text' => $textPreview,
            'viewer_url' => $viewerUrl,
            'url' => $url,
        ]);
    }

    /**
     * 查找元数据文件
     */
    private function findMetaFile(string $uploadId): ?string
    {
        $dateDirs = Storage::disk('local')->directories('uploads/chunks');
        foreach ($dateDirs as $dateDir) {
            $uploadDir = Storage::disk('local')->directories($dateDir);
            foreach ($uploadDir as $dir) {
                if (basename($dir) === $uploadId) {
                    $metaFile = $dir . '/.meta.json';
                    if (Storage::disk('local')->exists($metaFile)) {
                        return $metaFile;
                    }
                }
            }
        }
        return null;
    }

    /**
     * 合并分片
     */
    private function mergeChunks(array $meta): string
    {
        $chunksDir = storage_path('app/' . $meta['path']);
        $finalDir = 'chat-attachments/' . now()->format('Y/m/d');
        $finalName = pathinfo($meta['file_name'], PATHINFO_FILENAME)
            . '_' . substr($meta['upload_id'], 0, 8) . '.'
            . pathinfo($meta['file_name'], PATHINFO_EXTENSION);
        $finalPath = storage_path("app/public/{$finalDir}/{$finalName}");

        if (!is_dir(dirname($finalPath))) {
            mkdir(dirname($finalPath), 0755, true);
        }

        $finalHandle = fopen($finalPath, 'wb');
        for ($i = 0; $i < $meta['total_chunks']; $i++) {
            $chunkFile = "{$chunksDir}/chunk_{$i}";
            if (file_exists($chunkFile)) {
                $chunkHandle = fopen($chunkFile, 'rb');
                stream_copy_to_stream($chunkHandle, $finalHandle);
                fclose($chunkHandle);
            }
        }
        fclose($finalHandle);

        return Storage::url("{$finalDir}/{$finalName}");
    }

    /**
     * 计算最佳分片大小
     */
    private function optimalChunkSize(int $fileSize): int
    {
        if ($fileSize > 1073741824) return 10485760;  // >1GB: 10MB
        if ($fileSize > 524288000) return 5242880;    // >500MB: 5MB
        if ($fileSize > 104857600) return 2097152;    // >100MB: 2MB
        return 1048576;                                 // default: 1MB
    }
}

<?php

namespace App\Services;

use App\Models\CloudUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * M3-48 客户上传文件云存储
 */
class CloudUploadService
{
    /**
     * 上传文件
     */
    public function upload(UploadedFile $file, int $tenantId, string $type, ?int $userId = null, array $options = []): CloudUpload
    {
        $this->validateType($file, $type);

        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $hash = hash_file('sha256', $file->getRealPath());
        $disk = config('cloud-upload.storage.disk', 's3');
        $prefix = str_replace(['{tenant_id}', '{type}'], [$tenantId, $type], config('cloud-upload.storage.prefix', 'uploads/{tenant_id}/{type}'));
        $fileName = date('Ymd_His') . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();

        // 检查重复上传
        $existing = CloudUpload::where('tenant_id', $tenantId)
            ->where('hash', $hash)
            ->where('type', $type)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return $existing;
        }

        // 存储文件
        $path = $file->storeAs($prefix, $fileName, $disk);

        // 生成URL
        $isPublic = config("cloud-upload.types.{$type}.public", false);
        $url = null;
        $thumbnailUrl = null;

        if ($isPublic) {
            $url = Storage::disk($disk)->url($path);
        } else {
            if ($disk === 'local' || $disk === 'public') {
                $url = Storage::disk($disk)->url($path);
            } else {
                // 生成临时签名URL (仅云存储支持)
                $expiry = config('cloud-upload.storage.url_expiry_minutes', 60);
                try {
                    $url = Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($expiry));
                } catch (\Throwable $e) {
                    $url = Storage::disk($disk)->url($path);
                }
            }
        }

        // 生成缩略图(图片类型)
        if (str_starts_with($mimeType, 'image/') && $type !== 'screenshot') {
            $thumbnailPath = $this->generateThumbnail($file, $prefix, $fileName, $disk);
            if ($thumbnailPath) {
                $thumbnailUrl = $isPublic
                    ? Storage::disk($disk)->url($thumbnailPath)
                    : ($disk === 'local' || $disk === 'public'
                        ? Storage::disk($disk)->url($thumbnailPath)
                        : Storage::disk($disk)->temporaryUrl($thumbnailPath, now()->addMinutes(60)));
            }
        }

        return CloudUpload::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => $type,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'file_size' => $file->getSize(),
            'path' => $path,
            'url' => $url,
            'thumbnail_url' => $thumbnailUrl,
            'disk' => $disk,
            'hash' => $hash,
            'is_public' => $isPublic,
            'status' => 'active',
        ]);
    }

    /**
     * 获取文件URL
     */
    public function getUrl(CloudUpload $upload): string
    {
        if ($upload->is_public) {
            return $upload->url ?: Storage::disk($upload->disk)->url($upload->path);
        }

        return Storage::disk($upload->disk)->temporaryUrl(
            $upload->path,
            now()->addMinutes(config('cloud-upload.storage.url_expiry_minutes', 60))
        );
    }

    /**
     * 删除文件
     */
    public function delete(CloudUpload $upload): bool
    {
        Storage::disk($upload->disk)->delete($upload->path);

        if ($upload->thumbnail_url) {
            $thumbPath = str_replace(Storage::disk($upload->disk)->url(''), '', $upload->thumbnail_url);
            Storage::disk($upload->disk)->delete($thumbPath);
        }

        return $upload->update(['status' => 'deleted']) && $upload->delete();
    }

    /**
     * 获取仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        $totalFiles = CloudUpload::where('tenant_id', $tenantId)->where('status', 'active')->count();
        $totalSize = CloudUpload::where('tenant_id', $tenantId)->where('status', 'active')->sum('file_size');

        $byType = CloudUpload::where('tenant_id', $tenantId)->where('status', 'active')
            ->selectRaw('type, COUNT(*) as count, SUM(file_size) as total_size')
            ->groupBy('type')
            ->get()
            ->toArray();

        return [
            'total_files' => $totalFiles,
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1048576, 2),
            'by_type' => $byType,
        ];
    }

    protected function generateThumbnail(UploadedFile $file, string $prefix, string $fileName, string $disk): ?string
    {
        // 简单缩略图生成 - 实际应使用 Intervention Image
        $thumbName = 'thumb_' . $fileName;
        $thumbPath = $prefix . '/thumb_' . $fileName;

        try {
            $image = imagecreatefromstring(file_get_contents($file->getRealPath()));
            if (!$image) return null;

            $width = imagesx($image);
            $height = imagesy($image);
            $thumbWidth = min(200, $width);
            $thumbHeight = intval($height * ($thumbWidth / $width));

            $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
            imagecopyresampled($thumb, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

            ob_start();
            imagejpeg($thumb, null, 85);
            $thumbData = ob_get_clean();
            imagedestroy($image);
            imagedestroy($thumb);

            Storage::disk($disk)->put($thumbPath, $thumbData, 'public');
            return $thumbPath;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function validateType(UploadedFile $file, string $type): void
    {
        $allowedMimes = config("cloud-upload.storage.allowed_types.{$type}", ['*']);
        if ($allowedMimes !== ['*'] && !in_array($file->getMimeType(), $allowedMimes)) {
            throw new \InvalidArgumentException("文件类型不允许: {$file->getMimeType()}");
        }

        $maxSize = config('cloud-upload.storage.max_file_size_kb', 20480) * 1024;
        if ($file->getSize() > $maxSize) {
            throw new \InvalidArgumentException('文件大小超过限制');
        }
    }
}

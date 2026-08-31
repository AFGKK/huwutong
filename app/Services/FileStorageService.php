<?php

namespace App\Services;

use App\Contracts\CloudStorage;
use App\Models\Customer;
use App\Models\CustomerFile;
use App\Models\FileShareLink;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FileStorageService
{
    // 允许的文件类型
    const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'text/csv',
        'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
        'application/json', 'application/xml',
    ];

    // 最大文件大小（默认 50MB）
    const MAX_FILE_SIZE = 50 * 1024 * 1024;

    const CATEGORIES = ['invoice', 'receipt', 'contract', 'attachment', 'other'];

    public function __construct(
        protected CloudStorage $cloudStorage,
    ) {}

    /**
     * 上传文件
     */
    public function upload(UploadedFile $file, int $customerId, int $tenantId, array $options = []): CustomerFile
    {
        $this->validateFile($file);

        $category = $options['category'] ?? 'other';
        $description = $options['description'] ?? null;
        $visibility = $options['visibility'] ?? 'private';

        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        // 生成云存储路径: tenant/{customer_id}/{category}/{date}/{uuid}.ext
        $storagePath = sprintf(
            'customer-files/%d/%d/%s/%s/%s.%s',
            $tenantId,
            $customerId,
            $category,
            now()->format('Y-m-d'),
            (string) Str::uuid(),
            $extension
        );

        // 上传到云存储
        $this->cloudStorage->upload($storagePath, file_get_contents($file->getRealPath()), [
            'visibility' => $visibility === 'public' ? 'public' : 'private',
            'contentType' => $mimeType,
        ]);

        $disk = $this->cloudStorage->driver();

        return DB::transaction(function () use ($originalName, $storagePath, $mimeType, $fileSize, $extension, $disk, $visibility, $category, $description, $customerId, $tenantId) {
            return CustomerFile::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'original_name' => $originalName,
                'storage_path' => $storagePath,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'file_extension' => $extension,
                'disk' => $disk,
                'visibility' => $visibility,
                'category' => $category,
                'description' => $description,
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now(),
            ]);
        });
    }

    /**
     * 删除文件
     */
    public function delete(CustomerFile $file): bool
    {
        return DB::transaction(function () use ($file) {
            // 删除云存储文件
            $this->cloudStorage->delete($file->storage_path);
            // 软删除数据库记录
            return $file->delete();
        });
    }

    /**
     * 永久删除文件
     */
    public function forceDelete(CustomerFile $file): bool
    {
        return DB::transaction(function () use ($file) {
            $this->cloudStorage->delete($file->storage_path);
            $file->shareLinks()->delete();
            return $file->forceDelete();
        });
    }

    /**
     * 获取文件下载URL（私有文件返回临时签名URL）
     */
    public function getDownloadUrl(CustomerFile $file, int $expires = 3600): string
    {
        if ($file->visibility === 'public') {
            return $this->cloudStorage->url($file->storage_path);
        }
        return $this->cloudStorage->temporaryUrl($file->storage_path, $expires);
    }

    /**
     * 生成分享链接
     */
    public function createShareLink(CustomerFile $file, array $options = []): FileShareLink
    {
        $token = Str::random(40);
        $password = $options['password'] ?? null;
        $expiresAt = $options['expires_at'] ?? null;
        $maxDownloads = $options['max_downloads'] ?? null;

        return FileShareLink::create([
            'customer_file_id' => $file->id,
            'token' => $token,
            'password' => $password ? bcrypt($password) : null,
            'expires_at' => $expiresAt,
            'max_downloads' => $maxDownloads,
            'is_active' => true,
        ]);
    }

    /**
     * 通过分享令牌获取文件
     */
    public function getFileByShareToken(string $token): ?CustomerFile
    {
        $link = FileShareLink::where('token', $token)->first();

        if (!$link || !$link->isValid()) {
            return null;
        }

        return $link->file;
    }

    /**
     * 验证分享链接密码
     */
    public function verifySharePassword(FileShareLink $link, string $password): bool
    {
        if (!$link->password) return true;
        return \Illuminate\Support\Facades\Hash::check($password, $link->password);
    }

    /**
     * 记录下载
     */
    public function recordDownload(FileShareLink $link): void
    {
        $link->increment('download_count');
    }

    /**
     * 撤销分享链接
     */
    public function revokeShareLink(FileShareLink $link): bool
    {
        return $link->update(['is_active' => false]);
    }

    // ─── 查询方法 ───

    public function listFiles(int $tenantId, array $filters = [], int $perPage = 20)
    {
        $query = CustomerFile::with(['customer:id,user_id', 'uploader:id,name'])
            ->where('tenant_id', $tenantId);

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['search'])) {
            $query->where('original_name', 'like', "%{$filters['search']}%");
        }
        if (!empty($filters['date_from'])) {
            $query->where('uploaded_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('uploaded_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('uploaded_at')->paginate($perPage);
    }

    public function getFileDetail(int $id): CustomerFile
    {
        return CustomerFile::with([
            'customer:id,user_id', 'uploader:id,name', 'shareLinks',
        ])->findOrFail($id);
    }

    public function getStats(int $tenantId, ?int $customerId = null): array
    {
        $query = CustomerFile::where('tenant_id', $tenantId);
        if ($customerId) $query->where('customer_id', $customerId);

        $totalFiles = (clone $query)->count();
        $totalSize = (clone $query)->sum('file_size');
        $byCategory = (clone $query)
            ->selectRaw('category, count(*) as count, sum(file_size) as total_size')
            ->groupBy('category')
            ->get()
            ->keyBy('category')
            ->toArray();

        return [
            'total_files' => $totalFiles,
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'by_category' => $byCategory,
        ];
    }

    // ─── 校验 ───

    protected function validateFile(UploadedFile $file): void
    {
        // 检查文件大小
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \RuntimeException(__("app.file_storage.msg_416e0d5f") . ($this->formatBytes(self::MAX_FILE_SIZE)) . '）');
        }

        // 检查MIME类型
        $mime = $file->getMimeType();
        if (!in_array($mime, self::ALLOWED_MIME_TYPES)) {
            throw new \RuntimeException(__("app.file_storage.msg_41515ad9"));
        }
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

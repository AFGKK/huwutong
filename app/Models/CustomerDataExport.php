<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperCustomerDataExport
 */
class CustomerDataExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'tenant_id',
        'type', 'format', 'filters',
        'status', 'file_path', 'file_name', 'file_size',
        'record_count', 'error_message',
        'expires_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'file_size' => 'integer',
            'record_count' => 'integer',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    const TYPES = ['licenses', 'invoices', 'activations', 'customers'];
    const FORMATS = ['csv', 'pdf'];
    const STATUSES = ['pending', 'processing', 'completed', 'failed'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'completed')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'licenses' => 'License 列表',
            'invoices' => '发票/账单',
            'activations' => '激活记录',
            'customers' => '客户信息',
            default => $type,
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => '等待中',
            'processing' => '生成中',
            'completed' => '已完成',
            'failed' => '失败',
            default => $status,
        };
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markAsCompleted(string $filePath, string $fileName, int $fileSize, int $recordCount): void
    {
        $this->update([
            'status' => 'completed',
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'record_count' => $recordCount,
            'completed_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'completed_at' => now(),
        ]);
    }
}

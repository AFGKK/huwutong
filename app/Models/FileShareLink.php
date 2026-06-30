<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileShareLink extends Model
{
    protected $fillable = [
        'customer_file_id', 'token', 'password', 'expires_at',
        'max_downloads', 'download_count', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'max_downloads' => 'integer',
            'download_count' => 'integer',
        ];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(CustomerFile::class, 'customer_file_id');
    }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->max_downloads && $this->download_count >= $this->max_downloads) return false;
        return true;
    }
}

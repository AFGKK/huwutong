<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperLicenseFileRecord
 */
class LicenseFileRecord extends Model
{
    protected $fillable = [
        'license_id', 'file_key', 'original_filename', 'mime_type',
        'file_size', 'file_hash', 'signature', 'key_version', 'algorithm',
        'payload_snapshot', 'storage_driver', 'cdn_url', 'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'key_version' => 'integer',
            'payload_snapshot' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(CdnDistribution::class);
    }
}

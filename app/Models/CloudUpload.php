<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperCloudUpload
 */
class CloudUpload extends Model
{
    use SoftDeletes;

    protected $table = 'cloud_uploads';

    protected $fillable = [
        'tenant_id', 'user_id', 'type', 'original_name', 'mime_type',
        'file_size', 'path', 'url', 'thumbnail_url', 'disk',
        'hash', 'metadata', 'is_public', 'status',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_public' => 'boolean',
            'file_size' => 'integer',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}

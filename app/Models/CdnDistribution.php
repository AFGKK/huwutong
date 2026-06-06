<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CdnDistribution extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'license_file_record_id', 'client_ip', 'user_agent',
        'referer', 'country', 'response_code', 'bytes_served',
        'downloaded_at',
    ];

    protected function casts(): array
    {
        return [
            'response_code' => 'integer',
            'bytes_served' => 'integer',
            'downloaded_at' => 'datetime',
        ];
    }

    public function fileRecord(): BelongsTo
    {
        return $this->belongsTo(LicenseFileRecord::class);
    }
}

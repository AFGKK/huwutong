<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceAppVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_id', 'version', 'changelog', 'package_url',
        'min_platform_version', 'status', 'released_at',
    ];

    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
        ];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(MarketplaceApp::class, 'app_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpdatePackageDownload extends Model
{
    use HasFactory;

    protected $fillable = [
        'update_package_id',
        'tenant_id',
        'client_ip',
        'user_agent',
        'cdn_edge_ip',
        'downloaded_at',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(UpdatePackage::class, 'update_package_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

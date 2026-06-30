<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnershipTransferRecord extends Model
{
    protected $fillable = [
        'transfer_request_id', 'entity_type', 'entity_id', 'status', 'notes',
    ];

    public function transferRequest(): BelongsTo
    {
        return $this->belongsTo(OwnershipTransferRequest::class, 'transfer_request_id');
    }
}

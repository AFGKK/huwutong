<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseBatchItem extends Model
{
    protected $table = 'license_batch_items';

    protected $fillable = [
        'batch_generation_id', 'license_id', 'row_index',
        'variables', 'error_message', 'status',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
        ];
    }

    public function batch(): BelongsTo { return $this->belongsTo(LicenseBatchGeneration::class, 'batch_generation_id'); }
    public function license(): BelongsTo { return $this->belongsTo(License::class); }
}

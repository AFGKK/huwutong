<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperOaMaterial
 */
class OaMaterial extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_id',
        'type',
        'file_url',
        'content',
        'file_name',
        'file_size',
        'group',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(OfficialAccount::class, 'account_id');
    }
}

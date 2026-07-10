<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperApiDocFavorite
 */
class ApiDocFavorite extends Model
{
    use HasFactory;

    protected $table = 'api_doc_favorites';

    protected $fillable = [
        'user_id',
        'endpoint_id',
        'note',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(ApiDocEndpoint::class, 'endpoint_id');
    }
}

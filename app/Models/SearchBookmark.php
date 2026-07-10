<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSearchBookmark
 */
class SearchBookmark extends Model
{
    protected $table = 'search_bookmarks';

    protected $fillable = [
        'user_id', 'tenant_id', 'resource_type',
        'resource_id', 'label', 'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

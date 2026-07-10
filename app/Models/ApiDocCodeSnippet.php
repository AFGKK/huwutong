<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperApiDocCodeSnippet
 */
class ApiDocCodeSnippet extends Model
{
    protected $table = 'api_doc_code_snippets';

    protected $fillable = [
        'endpoint_id', 'language', 'title', 'code', 'description', 'sort_order',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(ApiDocEndpoint::class, 'endpoint_id');
    }
}

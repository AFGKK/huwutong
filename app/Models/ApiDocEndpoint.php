<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiDocEndpoint extends Model
{
    use HasFactory;

    protected $table = 'api_doc_endpoints';

    protected $fillable = [
        'api_version_id', 'method', 'path', 'summary', 'description',
        'group', 'tag', 'parameters', 'request_body', 'responses',
        'headers', 'security', 'example_request', 'example_response',
        'code_examples', 'metadata', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'request_body' => 'array',
            'responses' => 'array',
            'headers' => 'array',
            'security' => 'array',
            'example_request' => 'array',
            'example_response' => 'array',
            'code_examples' => 'array',
            'metadata' => 'array',
        ];
    }

    public function apiVersion(): BelongsTo
    {
        return $this->belongsTo(ApiVersion::class, 'api_version_id');
    }

    public function snippets(): HasMany
    {
        return $this->hasMany(ApiDocCodeSnippet::class, 'endpoint_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperApiTestRequest
 */
class ApiTestRequest extends Model
{
    use HasFactory;
    protected $table = 'api_test_requests';

    protected $fillable = [
        'user_id', 'endpoint_id', 'method', 'url',
        'headers', 'body', 'response', 'response_status',
        'response_time_ms', 'status', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'body' => 'array',
            'response' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(ApiDocEndpoint::class, 'endpoint_id');
    }
}

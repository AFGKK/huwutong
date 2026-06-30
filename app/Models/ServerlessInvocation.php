<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerlessInvocation extends Model
{
    protected $table = 'serverless_invocations';

    protected $fillable = [
        'serverless_function_id', 'invocation_id', 'token_id',
        'source_ip', 'duration_ms', 'status_code', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function function(): BelongsTo { return $this->belongsTo(ServerlessFunction::class, 'serverless_function_id'); }
}

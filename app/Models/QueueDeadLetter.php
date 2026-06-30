<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueDeadLetter extends Model
{
    protected $table = 'queue_dead_letters';

    protected $fillable = [
        'queue', 'job_class', 'payload', 'last_error', 'attempts',
        'status', 'failed_at', 'retried_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'failed_at' => 'datetime',
            'retried_at' => 'datetime',
        ];
    }
}

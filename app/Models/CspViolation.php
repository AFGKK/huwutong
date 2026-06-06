<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CspViolation extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_uri',
        'blocked_uri',
        'violated_directive',
        'effective_directive',
        'source_file',
        'line_number',
        'column_number',
        'status_code',
        'original_policy',
        'disposition',
        'user_agent',
        'reported_from',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperApiDocSchema
 */
class ApiDocSchema extends Model
{
    use HasFactory;
    protected $table = 'api_doc_schemas';

    protected $fillable = [
        'name', 'type', 'description', 'schema', 'example', 'properties',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'example' => 'array',
            'properties' => 'array',
        ];
    }
}

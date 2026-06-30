<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiDocTag extends Model
{
    protected $table = 'api_doc_tags';

    protected $fillable = [
        'name', 'label', 'description', 'sort_order',
    ];
}

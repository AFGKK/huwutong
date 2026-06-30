<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiSdkConfig extends Model
{
    protected $table = 'api_sdk_configs';

    protected $fillable = [
        'name', 'language', 'version', 'description',
        'config', 'install_command', 'setup_code', 'readme',
        'download_url', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }
}

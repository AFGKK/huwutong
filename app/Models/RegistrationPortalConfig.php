<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationPortalConfig extends Model
{
    protected $table = 'registration_portal_configs';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $record = static::where('key', $key)->first();
        return $record ? $record->value : $default;
    }

    public static function setValue(string $key, array $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}

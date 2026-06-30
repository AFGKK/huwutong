<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * M2-16 SDK版本
 *
 * @property int $id
 * @property string $language
 * @property string $version
 * @property string $stage preview|stable|deprecated|sunset
 * @property bool $is_current
 * @property bool $allow_production
 * @property string $min_api_version
 * @property string|null $changelog
 * @property string|null $upgrade_notes
 * @property string|null $compatible_sdk_versions
 * @property Carbon|null $released_at
 * @property Carbon|null $deprecated_at
 * @property Carbon|null $sunset_at
 */
class SdkVersion extends Model
{
    protected $fillable = [
        'language', 'version', 'stage', 'is_current', 'allow_production',
        'min_api_version', 'changelog', 'upgrade_notes', 'compatible_sdk_versions',
        'released_at', 'deprecated_at', 'sunset_at',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'allow_production' => 'boolean',
        'released_at' => 'datetime',
        'deprecated_at' => 'datetime',
        'sunset_at' => 'datetime',
    ];

    public const LANGUAGES = ['php', 'node', 'python', 'go', 'java'];
    public const STAGES = ['preview', 'stable', 'deprecated', 'sunset'];

    public function scopeCurrent($q) { return $q->where('is_current', true); }
    public function scopeByLanguage($q, $lang) { return $q->where('language', $lang); }
    public function scopeStable($q) { return $q->where('stage', 'stable'); }
    public function scopeActive($q) { return $q->whereIn('stage', ['preview', 'stable']); }
}

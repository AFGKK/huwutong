<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * M2-17 SDK完整性检查记录
 *
 * @property int $id
 * @property string $sdk_instance_id
 * @property string $language
 * @property string $sdk_version
 * @property string|null $machine_id
 * @property bool $passed
 * @property array|null $file_checksums
 * @property array|null $failed_files
 * @property string|null $error_message
 * @property string|null $client_ip
 */
class SdkIntegrityCheck extends Model
{
    protected $fillable = [
        'sdk_instance_id', 'language', 'sdk_version', 'machine_id',
        'passed', 'file_checksums', 'failed_files', 'error_message',
        'client_ip', 'checked_at',
    ];

    protected $casts = [
        'passed' => 'boolean',
        'file_checksums' => 'json',
        'failed_files' => 'json',
        'checked_at' => 'datetime',
    ];

    public function scopeFailed($q) { return $q->where('passed', false); }
    public function scopeByInstance($q, $id) { return $q->where('sdk_instance_id', $id); }
    public function scopeRecent($q) { return $q->orderByDesc('checked_at'); }
}

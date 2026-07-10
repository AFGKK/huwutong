<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperCertificateRevocationList
 */
class CertificateRevocationList extends Model
{
    protected $table = 'certificate_revocation_list';

    public $timestamps = false;

    protected $fillable = [
        'license_file_record_id', 'license_key', 'key_version',
        'reason', 'revoked_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'key_version' => 'integer',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}

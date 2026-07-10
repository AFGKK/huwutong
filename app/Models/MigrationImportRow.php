<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperMigrationImportRow
 */
class MigrationImportRow extends Model
{
    protected $table = 'migration_import_rows';

    protected $fillable = [
        'migration_import_id', 'row_number', 'original_data',
        'mapped_data', 'status', 'error_message',
        'created_license_id', 'created_customer_id',
    ];

    protected function casts(): array
    {
        return [
            'original_data' => 'array',
            'mapped_data' => 'array',
        ];
    }

    public function import(): BelongsTo { return $this->belongsTo(MigrationImport::class, 'migration_import_id'); }
}

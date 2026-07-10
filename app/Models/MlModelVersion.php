<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperMlModelVersion
 */
class MlModelVersion extends Model
{
    protected $table = 'ml_model_versions';

    protected $fillable = [
        'ml_model_id', 'version', 'file_path', 'file_hash', 'file_size',
        'metrics', 'hyperparameters', 'status', 'deployed_at', 'deployed_by',
    ];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'hyperparameters' => 'array',
            'deployed_at' => 'datetime',
        ];
    }

    public function model(): BelongsTo { return $this->belongsTo(MlModel::class, 'ml_model_id'); }
    public function deployer(): BelongsTo { return $this->belongsTo(User::class, 'deployed_by'); }
    public function driftEvents(): HasMany { return $this->hasMany(MlDriftEvent::class, 'ml_model_version_id'); }
}

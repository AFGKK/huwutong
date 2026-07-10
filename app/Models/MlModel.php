<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperMlModel
 */
class MlModel extends Model
{
    use SoftDeletes;

    protected $table = 'ml_models';

    protected $fillable = [
        'tenant_id', 'name', 'model_key', 'framework', 'task_type',
        'description', 'status', 'config', 'features', 'metrics_definitions',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'features' => 'array',
            'metrics_definitions' => 'array',
        ];
    }

    public function versions(): HasMany { return $this->hasMany(MlModelVersion::class, 'ml_model_id'); }
    public function trainingJobs(): HasMany { return $this->hasMany(MlTrainingJob::class, 'ml_model_id'); }
    public function productionVersion() { return $this->hasOne(MlModelVersion::class, 'ml_model_id')->where('status', 'production'); }
}

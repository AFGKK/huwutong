<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LicenseContractEvaluationLog extends Model
{
    use HasFactory;

    protected $table = 'license_contract_evaluation_logs';

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'evaluatable_type',
        'evaluatable_id',
        'context_type',
        'context_id',
        'contract_slug',
        'contract_name',
        'evaluation_mode',
        'result',
        'conditions_results',
        'matched_conditions',
        'failed_conditions',
        'reason',
        'context_data',
        'source_ip',
        'evaluation_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'conditions_results' => 'array',
            'matched_conditions' => 'array',
            'failed_conditions' => 'array',
            'context_data' => 'array',
            'evaluation_time_ms' => 'float',
        ];
    }

    const RESULTS = [
        'granted' => '已授权',
        'denied' => '已拒绝',
        'error' => '评估错误',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LicenseContract::class, 'contract_id');
    }

    public function evaluatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperConversionFunnelSummary
 */
class ConversionFunnelSummary extends Model
{
    protected $table = 'conversion_funnel_summaries';

    protected $fillable = [
        'tenant_id', 'date',
        'trial_registered', 'sdk_downloaded', 'sdk_activated',
        'first_validation', 'feature_used', 'converted',
        'conversion_rate', 'by_source',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'by_source' => 'array',
        ];
    }
}

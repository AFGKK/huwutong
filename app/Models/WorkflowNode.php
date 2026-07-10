<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperWorkflowNode
 */
class WorkflowNode extends Model
{
    use HasFactory;

    protected $table = 'workflow_nodes';

    protected $fillable = [
        'design_id', 'node_id', 'type', 'label', 'icon',
        'config', 'position', 'style', 'input_schema', 'output_schema',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'position' => 'array',
            'style' => 'array',
            'input_schema' => 'array',
            'output_schema' => 'array',
        ];
    }

    const TYPES = ['trigger', 'condition', 'action', 'approval', 'webhook', 'end'];

    public function design(): BelongsTo { return $this->belongsTo(WorkflowDesign::class, 'design_id'); }
}

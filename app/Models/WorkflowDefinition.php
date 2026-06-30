<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 工作流定义
 *
 * 描述一个工作流的结构：包含哪些步骤、每个步骤的配置（超时、重试、补偿）。
 */
class WorkflowDefinition extends Model
{
    protected $fillable = [
        'name', 'description', 'steps_definition', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'steps_definition' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function instances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class, 'workflow_name', 'name');
    }

    public function steps(): array
    {
        return $this->steps_definition ?? [];
    }

    public function step(string $name): ?array
    {
        foreach ($this->steps() as $step) {
            if ($step['name'] === $name) {
                return $step;
            }
        }
        return null;
    }
}

<?php

namespace App\Workflows;

use App\Models\WorkflowInstance;
use Illuminate\Support\Facades\Log;

/**
 * 基础工作流步骤 — 提供默认实现
 */
abstract class BaseWorkflowStep implements WorkflowStep
{
    public function maxRetries(): int
    {
        return 3;
    }

    public function retryDelay(): array|int
    {
        return [10, 30, 60];
    }

    public function timeout(): int
    {
        return 300;
    }

    public function description(): string
    {
        return $this->name();
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        Log::$level("[Workflow:{$this->name()}] {$message}", $context);
    }
}

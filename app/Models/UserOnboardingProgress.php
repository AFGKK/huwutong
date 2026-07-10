<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperUserOnboardingProgress
 */
class UserOnboardingProgress extends Model
{
    protected $table = 'user_onboarding_progress';

    protected $fillable = [
        'user_id', 'current_step', 'completed_steps',
        'is_completed', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_steps' => 'array',
            'is_completed' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    const STEPS = [
        'welcome',
        'profile',
        'tenant',
        'product',
        'api_key',
        'complete',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isStepCompleted(string $step): bool
    {
        return in_array($step, $this->completed_steps ?? []);
    }

    public function completeStep(string $step): void
    {
        $steps = $this->completed_steps ?? [];
        if (!in_array($step, $steps)) {
            $steps[] = $step;
        }

        $isComplete = empty(array_diff(self::STEPS, ['complete'], $steps));

        $this->update([
            'current_step' => $this->getNextStep($step),
            'completed_steps' => $steps,
            'is_completed' => $isComplete,
            'completed_at' => $isComplete ? now() : null,
        ]);

        if ($isComplete) {
            $this->user->update(['onboarding_completed' => true]);
        }
    }

    protected function getNextStep(string $currentStep): string
    {
        $index = array_search($currentStep, self::STEPS);
        if ($index !== false && isset(self::STEPS[$index + 1])) {
            return self::STEPS[$index + 1];
        }
        return 'complete';
    }
}

<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceLifecycleEvent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceLifecycleEventFactory extends Factory
{
    protected $model = DeviceLifecycleEvent::class;

    public function definition(): array
    {
        $trustBefore = fake()->numberBetween(0, 100);
        $delta = fake()->numberBetween(-30, 30);
        $trustAfter = max(0, min(100, $trustBefore + $delta));

        return [
            'device_id' => Device::factory(),
            'tenant_id' => fn(array $attrs) => Device::find($attrs['device_id'])?->tenant_id ?? Tenant::factory(),
            'event_type' => fake()->randomElement(['首次出现', '信任建立', '活跃稳定', '信任下降', '异常行为', '可疑标记', '废弃']),
            'stage' => fake()->randomElement(['new', 'onboarding', 'stable', 'suspicious', 'retired']),
            'trust_score_before' => $trustBefore,
            'trust_score_after' => $trustAfter,
            'trust_score_change' => $delta,
            'reason' => fake()->sentence(),
            'triggered_by' => 'system',
        ];
    }

    public function stageChange(string $fromStage, string $toStage): static
    {
        return $this->state(fn(array $attrs) => [
            'event_type' => match($toStage) {
                'new' => '首次出现',
                'onboarding' => '信任建立',
                'stable' => '活跃稳定',
                'suspicious' => '异常行为',
                'retired' => '废弃',
                default => 'stage_change',
            },
            'stage' => $toStage,
            'trust_score_before' => fake()->numberBetween(0, 100),
            'trust_score_after' => fake()->numberBetween(0, 100),
        ]);
    }
}

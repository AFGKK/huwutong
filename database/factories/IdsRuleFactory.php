<?php

namespace Database\Factories;

use App\Models\IdsRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class IdsRuleFactory extends Factory
{
    protected $model = IdsRule::class;

    public function definition(): array
    {
        return [
            'tenant_id' => \App\Models\Tenant::factory(),
            'name' => $this->faker->words(3, true) . '检测',
            'slug' => $this->faker->unique()->slug(2),
            'description' => $this->faker->sentence(),
            'detection_type' => $this->faker->randomElement(array_keys(IdsRule::DETECTION_TYPES)),
            'severity' => $this->faker->randomElement(['info', 'warning', 'critical']),
            'threshold_count' => $this->faker->randomElement([3, 5, 10, 20]),
            'threshold_window_minutes' => $this->faker->randomElement([1, 5, 10, 30]),
            'is_active' => true,
            'is_system' => false,
            'priority' => $this->faker->numberBetween(10, 200),
            'hit_count' => 0,
            'conditions' => ['event_type' => 'login_failed', 'group_by' => 'ip_address'],
            'actions' => [['type' => 'notify_admin']],
        ];
    }

    public function bruteForce(): static
    {
        return $this->state(fn(array $attrs) => [
            'detection_type' => 'brute_force',
            'threshold_count' => 5,
            'threshold_window_minutes' => 5,
            'severity' => 'critical',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn(array $attrs) => [
            'is_active' => false,
        ]);
    }

    public function system(): static
    {
        return $this->state(fn(array $attrs) => [
            'is_system' => true,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\UserBehavior;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserBehaviorFactory extends Factory
{
    protected $model = UserBehavior::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => null,
            'customer_id' => null,
            'event_type' => $this->faker->randomElement(['page_view', 'feature_use', 'license_action', 'login']),
            'event_action' => $this->faker->word(),
            'occurred_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function pageView(): static
    {
        return $this->state(fn(array $attr) => ['event_type' => 'page_view', 'event_action' => 'view_dashboard']);
    }

    public function featureUse(): static
    {
        return $this->state(fn(array $attr) => ['event_type' => 'feature_use', 'event_action' => 'api_validate']);
    }
}

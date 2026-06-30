<?php

namespace Database\Factories;

use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SecurityEventFactory extends Factory
{
    protected $model = SecurityEvent::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'event_type' => $this->faker->randomElement(SecurityEvent::EVENT_TYPES),
            'severity' => $this->faker->randomElement(SecurityEvent::SEVERITIES),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'description' => $this->faker->sentence(),
            'metadata' => ['extra' => 'data'],
        ];
    }

    public function loginFailed(): static
    {
        return $this->state(fn(array $attrs) => [
            'event_type' => 'login_failed',
            'severity' => 'warning',
        ]);
    }

    public function critical(): static
    {
        return $this->state(fn(array $attrs) => [
            'severity' => 'critical',
        ]);
    }
}

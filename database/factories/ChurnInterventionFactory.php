<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChurnInterventionFactory extends Factory
{
    protected $model = \App\Models\ChurnIntervention::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => \App\Models\Customer::factory(),
            'type' => $this->faker->randomElement(['renewal_call', 'coupon_offer', 'training_session', 'executive_engagement', 'survey']),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'assigned_to' => $this->faker->name(),
            'status' => 'pending',
        ];
    }
}

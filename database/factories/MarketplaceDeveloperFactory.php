<?php

namespace Database\Factories;

use App\Models\MarketplaceDeveloper;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketplaceDeveloperFactory extends Factory
{
    protected $model = MarketplaceDeveloper::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'display_name' => $this->faker->company(),
            'company_name' => $this->faker->company(),
            'website' => $this->faker->url(),
            'description' => $this->faker->paragraph(),
            'status' => 'active',
            'verified_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending', 'verified_at' => null]);
    }
}

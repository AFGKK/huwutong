<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FeatureFlagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => 'feature_' . fake()->unique()->bothify('##??'),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}

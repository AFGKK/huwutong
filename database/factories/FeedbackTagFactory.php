<?php

namespace Database\Factories;

use App\Models\FeedbackTag;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeedbackTagFactory extends Factory
{
    protected $model = FeedbackTag::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'color' => $this->faker->hexColor(),
        ];
    }
}
